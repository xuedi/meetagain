import { defaultJourney } from './userMix.js';

export const options = {
    scenarios: {
        quick: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '5s', target: 50 },
                { duration: '20s', target: 50 },
                { duration: '5s', target: 0 },
            ],
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

export default function () {
    defaultJourney();
}
