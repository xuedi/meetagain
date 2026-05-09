// Small-load scenario: 50 concurrent users, today's plausible real load on
// meetagain.org. Confirms the app comfortably handles current traffic.
//
// Run: docker compose --profile tools run --rm loadtest run /scripts/load/small.js

import { defaultJourney } from './userMix.js';
import { sharedThresholds } from '../lib/thresholds.js';

export const options = {
    scenarios: {
        small: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 50 },
                { duration: '2m', target: 50 },
                { duration: '20s', target: 0 },
            ],
            gracefulStop: '20s',
        },
    },
    thresholds: sharedThresholds,
    summaryTrendStats: ['avg', 'min', 'med', 'p(95)', 'p(99)', 'max'],
};

export default function () {
    defaultJourney();
}
