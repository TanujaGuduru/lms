<?php

// Model selection / tiering — docs/student-module/05a §3. A config-driven
// routing table, not a fact baked into application code: a tier can be
// repointed without touching a feature's code. Keys are "feature.mode",
// matching the doc's own table exactly.
return [
    'doubt_solver.hint' => 'fast',
    'doubt_solver.explain' => 'deep',
    'doubt_solver.practice' => 'fast',
    'coding_assistant.debug' => 'deep',
    'coding_assistant.review' => 'deep',
    'doubt_solver.escalate' => 'deep',
    'coding_assistant.escalate' => 'deep',
    'notebook.summarize' => 'deep',
    'notebook.flashcards' => 'deep',
    'notebook.quiz_generation' => 'deep',
    'notebook.generate_notes' => 'deep', // the map-reduce "reduce" step — quality of the reorganized note matters most
    'notebook.generate_notes_map' => 'fast', // the map-reduce "map" step (05c §2) — many cheap calls, not one expensive one
    'parent_report.summary' => 'deep',
    'project.originality_check' => 'fast',
    'placement.communication_score' => 'fast', // runs synchronously while the student waits — shares the Doubt Solver's <3s-class latency target (05d §1)
    'recommendation.narration' => 'fast', // narration of an already-decided pick, not the decision itself (05d §3)
];
