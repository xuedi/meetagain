// Test credentials drawn from src/DataFixtures/UserFixture.php and MinimalAdminFixture.php.
// All fixture users share password '1234'. Run `just devModeFixtures` to seed.

export const ADMIN = {
    email: 'admin@example.org',
    password: '1234',
};

// Member fixtures (subset - extend as scenarios need more).
export const MEMBERS = [
    { email: 'Abraham.Baker@example.org', password: '1234' },
    { email: 'Adem.Lane@example.org', password: '1234' },
    { email: 'Adil.Floyd@example.org', password: '1234' },
    { email: 'Alec.Whitten@example.org', password: '1234' },
    { email: 'Alesha.Barry@example.org', password: '1234' },
];

export function pickMember(vu) {
    return MEMBERS[(vu - 1) % MEMBERS.length];
}
