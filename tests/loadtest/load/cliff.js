// Find-the-cliff scenario: ramp arrival rate from 10 RPS to 1000 RPS over
// 10 minutes; abort early if SLO breaks. The dashboard shows the load level
// at which P95 first crossed 1s or error rate first exceeded 1%.
//
// Use this for hardware-sizing decisions, not regression detection.
//
// Run: docker compose --profile tools run --rm loadtest run /scripts/load/cliff.js

import { defaultJourney } from './userMix.js';
import { cliffThresholds } from '../lib/thresholds.js';

export const options = {
    scenarios: {
        cliff: {
            executor: 'ramping-arrival-rate',
            startRate: 10,
            timeUnit: '1s',
            preAllocatedVUs: 200,
            maxVUs: 2000,
            stages: [
                { duration: '2m', target: 50 },
                { duration: '3m', target: 200 },
                { duration: '3m', target: 500 },
                { duration: '2m', target: 1000 },
            ],
        },
    },
    thresholds: cliffThresholds,
    summaryTrendStats: ['avg', 'min', 'med', 'p(95)', 'p(99)', 'max'],
};

export default function () {
    defaultJourney();
}
