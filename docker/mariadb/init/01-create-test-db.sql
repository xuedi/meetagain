-- Create test database and grant permissions to the app user.
-- The pattern grant covers paratest's per-worker clones (meetAgain_test1, _test2, ...)
-- which `just testSetup` creates/drops at will.
CREATE DATABASE IF NOT EXISTS meetAgain_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `meetAgain\_test%`.* TO 'meetAgain'@'%';
FLUSH PRIVILEGES;
