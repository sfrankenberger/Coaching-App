<?php

/*
| KI (Anthropic). Der Schluessel gehoert der Plattform (ANTHROPIC_API_KEY),
| ein Mandant kann in settings.ai.anthropic_key einen eigenen hinterlegen.
| Qualitaet vor Tempo: Opus.
*/
return [
    'anthropic_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('AI_MODEL', 'claude-opus-5-5'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 6000),
    'timeout' => (int) env('AI_TIMEOUT', 240),
];
