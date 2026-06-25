/**
 * Pure peer-to-peer WebRTC, two participants only (a parent and the
 * teacher/admin host on the other end) — see PtmController's class
 * docblock for why this exists: the doc only describes a `meeting_link`
 * existing, not how the call itself works, and there's no managed video
 * service of any kind in this build. Deliberately simpler than
 * classroom.js: no chat, no heartbeat/presence-discovery loop, since
 * `join()` already tells the client exactly who the other participant is
 * — a PTM booking only ever has two people, never a changing roster.
 */
(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const bookingId = new URLSearchParams(window.location.search).get('booking_id');
  if (!bookingId) {
    window.location.href = 'dashboard.html';
    return;
  }

  const SIGNAL_POLL_MS = 1500;

  let selfUserId = null;
  let otherUserId = null;
  let otherUserName = 'Participant';
  let localStream = null;
  let iceServers = [{ urls: 'stun:stun.l.google.com:19302' }];
  let pc = null;
  let polling = true;

  const selfVideo = document.getElementById('video-self');
  const videoGrid = document.getElementById('video-grid');
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

  function createPeerConnection() {
    const connection = new RTCPeerConnection({ iceServers });

    if (localStream) {
      localStream.getTracks().forEach((track) => connection.addTrack(track, localStream));
    }

    connection.onicecandidate = (event) => {
      if (event.candidate) {
        Api.post(`/ptm/bookings/${bookingId}/signal`, {
          to_user_id: otherUserId,
          type: 'ice_candidate',
          payload: event.candidate.toJSON(),
        }).catch(() => {});
      }
    };

    connection.ontrack = (event) => attachRemoteStream(event.streams[0]);

    return connection;
  }

  function attachRemoteStream(stream) {
    let tile = document.getElementById('tile-other');
    if (!tile) {
      tile = document.createElement('div');
      tile.className = 'video-tile';
      tile.id = 'tile-other';

      const video = document.createElement('video');
      video.autoplay = true;
      video.playsInline = true;

      const label = document.createElement('span');
      label.className = 'tile-label';
      label.textContent = otherUserName;

      tile.append(video, label);
      videoGrid.appendChild(tile);
    }

    const video = tile.querySelector('video');
    if (video.srcObject !== stream) {
      video.srcObject = stream;
    }
  }

  function removeOtherTile() {
    document.getElementById('tile-other')?.remove();
  }

  async function connectToOther() {
    pc = createPeerConnection();

    // Deterministic glare avoidance, same convention as classroom.js — the
    // lower user_id always initiates the offer.
    if (selfUserId < otherUserId) {
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      await Api.post(`/ptm/bookings/${bookingId}/signal`, {
        to_user_id: otherUserId,
        type: 'offer',
        payload: { sdp: offer.sdp, type: offer.type },
      });
    }
  }

  async function handleSignal(message) {
    if (message.type === 'leave') {
      removeOtherTile();
      pc?.close();
      pc = null;
      return;
    }
    if (!pc) {
      pc = createPeerConnection();
    }

    if (message.type === 'offer') {
      await pc.setRemoteDescription(new RTCSessionDescription(message.payload));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      await Api.post(`/ptm/bookings/${bookingId}/signal`, {
        to_user_id: otherUserId,
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
    if (!polling) return;
    try {
      const messages = await Api.get(`/ptm/bookings/${bookingId}/signal`);
      for (const message of messages) {
        await handleSignal(message);
      }
    } catch {
      // Keep polling even if one round trip fails.
    }
    setTimeout(pollSignalsLoop, SIGNAL_POLL_MS);
  }

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

  // No server-relayed "leave" signal here — PtmController's client-facing
  // signal() only accepts offer/answer/ice_candidate (a 'leave' row is
  // something only ClassroomController inserts server-side, for its own
  // attendance-driven leave()). Closing the connection is enough: the other
  // side's RTCPeerConnection detects the disconnect via its own ICE/
  // connection-state events, standard WebRTC behavior, no relay needed for
  // a 2-person call with nothing else (chat, attendance) to finalize.
  document.getElementById('btn-leave').addEventListener('click', () => {
    polling = false;
    pc?.close();
    localStream?.getTracks().forEach((track) => track.stop());
    window.location.href = 'dashboard.html';
  });

  async function init() {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      selfVideo.srcObject = localStream;
    } catch {
      showError('Camera/microphone access was denied or is unavailable.');
    }

    try {
      const joinData = await Api.post(`/ptm/bookings/${bookingId}/join`);
      selfUserId = joinData.self.user_id;
      otherUserId = joinData.other.user_id;
      otherUserName = joinData.other.name || otherUserName;
      iceServers = joinData.ice_servers;
      await connectToOther();
      setConnected();
    } catch (err) {
      showError(err.message);
      return;
    }

    pollSignalsLoop();
  }

  init();
})();
