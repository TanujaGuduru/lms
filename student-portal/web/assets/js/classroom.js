/**
 * Pure peer-to-peer WebRTC mesh — every participant's browser connects
 * directly to every other participant's. The server (classes/{id}/signal)
 * only ever relays the connection-setup messages (SDP offers/answers, ICE
 * candidates); no video/audio frame ever passes through it. See
 * ClassroomController's class docblock for why this exists instead of a
 * managed video service.
 */
(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const classId = new URLSearchParams(window.location.search).get('class_id');
  if (!classId) {
    window.location.href = 'dashboard.html';
    return;
  }

  const SIGNAL_POLL_MS = 1500;
  const CHAT_POLL_MS = 2000;
  const HEARTBEAT_MS = 30000;

  let selfUserId = null;
  let localStream = null;
  let iceServers = [{ urls: 'stun:stun.l.google.com:19302' }];
  let lastChatId = 0;
  const peers = new Map(); // user_id -> { pc, name }

  const videoGrid = document.getElementById('video-grid');
  const selfVideo = document.getElementById('video-self');
  const statusPill = document.getElementById('connection-status');
  const alertBox = document.getElementById('alert-box');

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  function setConnected() {
    statusPill.textContent = 'connected';
    statusPill.classList.add('connected');
  }

  function createPeerConnection(peerId, peerName) {
    const pc = new RTCPeerConnection({ iceServers });

    if (localStream) {
      localStream.getTracks().forEach((track) => pc.addTrack(track, localStream));
    }

    pc.onicecandidate = (event) => {
      if (event.candidate) {
        Api.post(`/classes/${classId}/signal`, {
          to_user_id: peerId,
          type: 'ice_candidate',
          payload: event.candidate.toJSON(),
        }).catch(() => {});
      }
    };

    pc.ontrack = (event) => attachRemoteStream(peerId, peerName, event.streams[0]);

    peers.set(peerId, { pc, name: peerName });
    return pc;
  }

  function attachRemoteStream(peerId, peerName, stream) {
    let tile = document.getElementById(`tile-${peerId}`);
    if (!tile) {
      tile = document.createElement('div');
      tile.className = 'video-tile';
      tile.id = `tile-${peerId}`;

      const video = document.createElement('video');
      video.autoplay = true;
      video.playsInline = true;

      const label = document.createElement('span');
      label.className = 'tile-label';
      label.textContent = peerName;

      tile.append(video, label);
      videoGrid.appendChild(tile);
    }

    const video = tile.querySelector('video');
    if (video.srcObject !== stream) {
      video.srcObject = stream;
    }
  }

  function removePeer(peerId) {
    const entry = peers.get(peerId);
    if (entry) {
      entry.pc.close();
      peers.delete(peerId);
    }
    document.getElementById(`tile-${peerId}`)?.remove();
  }

  async function connectToPeer(peerId, peerName) {
    if (peers.has(peerId)) {
      return;
    }
    const pc = createPeerConnection(peerId, peerName);

    // Deterministic glare avoidance — the lower user_id always initiates
    // the offer, so two peers discovering each other at the same moment
    // never both try to be the offerer.
    if (selfUserId < peerId) {
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      await Api.post(`/classes/${classId}/signal`, {
        to_user_id: peerId,
        type: 'offer',
        payload: { sdp: offer.sdp, type: offer.type },
      });
    }
  }

  async function handleSignal(message) {
    const peerId = message.from_user_id;

    if (message.type === 'leave') {
      removePeer(peerId);
      return;
    }

    if (!peers.has(peerId)) {
      createPeerConnection(peerId, `Student ${peerId}`);
    }
    const { pc } = peers.get(peerId);

    if (message.type === 'offer') {
      await pc.setRemoteDescription(new RTCSessionDescription(message.payload));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      await Api.post(`/classes/${classId}/signal`, {
        to_user_id: peerId,
        type: 'answer',
        payload: { sdp: answer.sdp, type: answer.type },
      });
    } else if (message.type === 'answer') {
      await pc.setRemoteDescription(new RTCSessionDescription(message.payload));
    } else if (message.type === 'ice_candidate') {
      try {
        await pc.addIceCandidate(new RTCIceCandidate(message.payload));
      } catch {
        // Benign if a candidate arrives before the remote description is set.
      }
    }
  }

  async function pollSignalsLoop() {
    try {
      const messages = await Api.get(`/classes/${classId}/signal`);
      for (const message of messages) {
        await handleSignal(message);
      }
    } catch {
      // Keep polling even if one round trip fails.
    }
    setTimeout(pollSignalsLoop, SIGNAL_POLL_MS);
  }

  async function heartbeatLoop() {
    try {
      const data = await Api.post(`/classes/${classId}/heartbeat`);
      setConnected();
      for (const participant of data.participants) {
        if (!peers.has(participant.user_id)) {
          connectToPeer(participant.user_id, participant.name);
        }
      }
    } catch {
      // Transient network hiccup — try again next tick.
    }
    setTimeout(heartbeatLoop, HEARTBEAT_MS);
  }

  async function pollChatLoop() {
    try {
      const messages = await Api.get(`/classes/${classId}/chat?after_id=${lastChatId}`);
      if (messages.length) {
        const box = document.getElementById('chat-messages');
        for (const message of messages) {
          lastChatId = Math.max(lastChatId, message.id);
          const row = document.createElement('div');
          row.className = 'msg';
          const who = document.createElement('span');
          who.className = 'who';
          who.textContent = message.name;
          row.append(who, document.createTextNode(`: ${message.message}`));
          box.appendChild(row);
        }
        box.scrollTop = box.scrollHeight;
      }
    } catch {
      // Keep polling.
    }
    setTimeout(pollChatLoop, CHAT_POLL_MS);
  }

  document.getElementById('chat-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    try {
      await Api.post(`/classes/${classId}/chat`, { message: text });
    } catch {
      // Dropped messages aren't worth blocking the UI over here.
    }
  });

  document.getElementById('btn-chat').addEventListener('click', () => {
    document.getElementById('chat-panel').classList.toggle('hidden');
  });

  document.getElementById('btn-mic').addEventListener('click', (event) => {
    const track = localStream?.getAudioTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    event.target.classList.toggle('active', track.enabled);
    event.target.classList.toggle('off', !track.enabled);
  });

  document.getElementById('btn-cam').addEventListener('click', (event) => {
    const track = localStream?.getVideoTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    event.target.classList.toggle('active', track.enabled);
    event.target.classList.toggle('off', !track.enabled);
  });

  document.getElementById('btn-leave').addEventListener('click', async () => {
    try {
      await Api.post(`/classes/${classId}/leave`);
    } catch {
      // Leaving the page is more important than this call succeeding.
    }
    peers.forEach((entry) => entry.pc.close());
    localStream?.getTracks().forEach((track) => track.stop());
    window.location.href = 'dashboard.html';
  });

  window.addEventListener('beforeunload', () => {
    // Best-effort only — browsers don't reliably wait for async work during
    // unload. The server-side heartbeat timeout (ClassroomController) is the
    // real safety net for a tab closed without a clean leave.
    navigator.sendBeacon?.(`/api/v1/classes/${classId}/leave`);
  });

  async function init() {
    try {
      const detail = await Api.get(`/classes/${classId}`);
      document.getElementById('class-title').textContent = detail.title;
    } catch (err) {
      showError(err.message);
    }

    try {
      localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      selfVideo.srcObject = localStream;
    } catch {
      showError('Camera/microphone access was denied or is unavailable — you can still join with chat only.');
    }

    try {
      const joinData = await Api.post(`/classes/${classId}/join`);
      selfUserId = joinData.self.user_id;
      iceServers = joinData.ice_servers;
      for (const participant of joinData.participants) {
        await connectToPeer(participant.user_id, participant.name);
      }
      setConnected();
    } catch (err) {
      showError(err.message);
      return;
    }

    pollSignalsLoop();
    heartbeatLoop();
    pollChatLoop();
  }

  init();
})();
