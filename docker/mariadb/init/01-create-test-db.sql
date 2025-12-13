-- Create test database and grant permissions to the app user
CREATE DATABASE IF NOT EXISTS meetAgain_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON meetAgain_test.* TO 'meetAgain'@'%';
FLUSH PRIVILEGES;
