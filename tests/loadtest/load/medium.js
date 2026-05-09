// Medium-load scenario: ~500 concurrent users. 10x growth scenario, probes for
// early bottlenecks (DB pool, Valkey, CMS page cache).
//
// Run: docker compose --profile tools run --rm loadtest run /scripts/load/medium.js

import { defaultJourney } from './userMix.js';
import { sharedThresholds } from '../lib/thresholds.js';

export const options = {
    scenarios: {
        medium: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 500 },
                { duration: '5m', target: 500 },
                { duration: '30s', target: 0 },
            ],
            gracefulStop: '30s',
        },
    },
    thresholds: sharedThresholds,
    summaryTrendStats: ['avg', 'min', 'med', 'p(95)', 'p(99)', 'max'],
};

export default function () {
    defaultJourney();
}
