<?php declare(strict_types=1);

namespace ModuleGeoMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031152434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Geo module 1.0 - countries, states, cities, districts and their translations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE mod_geo_city (id INT AUTO_INCREMENT NOT NULL, geoname_id INT NOT NULL, ascii_name VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL, name_slug VARCHAR(200) NOT NULL, latitude NUMERIC(10, 7) NOT NULL, longitude NUMERIC(10, 7) NOT NULL, timezone VARCHAR(40) NOT NULL, population INT NOT NULL, feature_code VARCHAR(10) NOT NULL, country_id INT NOT NULL, state_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_37BD118723F5422B (geoname_id), INDEX idx_geo_city_name_slug (name_slug), INDEX idx_geo_city_population (population), UNIQUE INDEX uniq_geo_city_slug (country_id, slug), INDEX IDX_37BD1187F92F3E70 (country_id), INDEX IDX_37BD11875D83CC1 (state_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_geo_city_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(5) NOT NULL, name VARCHAR(200) NOT NULL, name_slug VARCHAR(200) NOT NULL, city_id INT NOT NULL, INDEX idx_geo_city_translation_name (language, name), INDEX idx_geo_city_translation_slug (name_slug), UNIQUE INDEX uniq_geo_city_translation (city_id, language), INDEX IDX_A0ABC7678BAC62AF (city_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_geo_country (id INT AUTO_INCREMENT NOT NULL, iso2 VARCHAR(2) NOT NULL, iso3 VARCHAR(3) NOT NULL, name VARCHAR(200) NOT NULL, name_slug VARCHAR(200) NOT NULL, numeric_code INT DEFAULT NULL, geoname_id INT NOT NULL, phone_code VARCHAR(20) DEFAULT NULL, currency_code VARCHAR(3) DEFAULT NULL, continent VARCHAR(2) DEFAULT NULL, capital_geoname_id INT DEFAULT NULL, population INT NOT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, bbox_north NUMERIC(10, 7) DEFAULT NULL, bbox_south NUMERIC(10, 7) DEFAULT NULL, bbox_east NUMERIC(10, 7) DEFAULT NULL, bbox_west NUMERIC(10, 7) DEFAULT NULL, UNIQUE INDEX UNIQ_BCED8FEA1B6F9774 (iso2), UNIQUE INDEX UNIQ_BCED8FEA6C68A7E2 (iso3), UNIQUE INDEX UNIQ_BCED8FEA23F5422B (geoname_id), INDEX idx_geo_country_name_slug (name_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_geo_country_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(5) NOT NULL, name VARCHAR(200) NOT NULL, name_slug VARCHAR(200) NOT NULL, country_id INT NOT NULL, INDEX idx_geo_country_translation_name (language, name), INDEX idx_geo_country_translation_slug (name_slug), UNIQUE INDEX uniq_geo_country_translation (country_id, language), INDEX IDX_C0236809F92F3E70 (country_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_geo_district (id INT AUTO_INCREMENT NOT NULL, geoname_id INT DEFAULT NULL, ascii_name VARCHAR(200) NOT NULL, slug VARCHAR(200) NOT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, population INT DEFAULT NULL, city_id INT NOT NULL, UNIQUE INDEX UNIQ_D52005CA23F5422B (geoname_id), UNIQUE INDEX uniq_geo_district_slug (city_id, slug), INDEX IDX_D52005CA8BAC62AF (city_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_geo_district_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(5) NOT NULL, name VARCHAR(200) NOT NULL, name_slug VARCHAR(200) NOT NULL, district_id INT NOT NULL, INDEX idx_geo_district_translation_name (language, name), INDEX idx_geo_district_translation_slug (name_slug), UNIQUE INDEX uniq_geo_district_translation (district_id, language), INDEX IDX_30DD6C04B08FA272 (district_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_geo_state (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, geoname_id INT NOT NULL, ascii_name VARCHAR(200) NOT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, population INT DEFAULT NULL, country_id INT NOT NULL, UNIQUE INDEX UNIQ_F1E1D6DE23F5422B (geoname_id), UNIQUE INDEX uniq_geo_state_code (country_id, code), INDEX IDX_F1E1D6DEF92F3E70 (country_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_geo_state_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(5) NOT NULL, name VARCHAR(200) NOT NULL, name_slug VARCHAR(200) NOT NULL, state_id INT NOT NULL, INDEX idx_geo_state_translation_name (language, name), INDEX idx_geo_state_translation_slug (name_slug), UNIQUE INDEX uniq_geo_state_translation (state_id, language), INDEX IDX_E11E820D5D83CC1 (state_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql('ALTER TABLE mod_geo_city ADD CONSTRAINT FK_37BD1187F92F3E70 FOREIGN KEY (country_id) REFERENCES mod_geo_country (id)');
        $this->addSql('ALTER TABLE mod_geo_city ADD CONSTRAINT FK_37BD11875D83CC1 FOREIGN KEY (state_id) REFERENCES mod_geo_state (id)');
        $this->addSql(
            'ALTER TABLE mod_geo_city_translation ADD CONSTRAINT FK_A0ABC7678BAC62AF FOREIGN KEY (city_id) REFERENCES mod_geo_city (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE mod_geo_country_translation ADD CONSTRAINT FK_C0236809F92F3E70 FOREIGN KEY (country_id) REFERENCES mod_geo_country (id) ON DELETE CASCADE',
        );
        $this->addSql('ALTER TABLE mod_geo_district ADD CONSTRAINT FK_D52005CA8BAC62AF FOREIGN KEY (city_id) REFERENCES mod_geo_city (id) ON DELETE CASCADE');
        $this->addSql(
            'ALTER TABLE mod_geo_district_translation ADD CONSTRAINT FK_30DD6C04B08FA272 FOREIGN KEY (district_id) REFERENCES mod_geo_district (id) ON DELETE CASCADE',
        );
        $this->addSql('ALTER TABLE mod_geo_state ADD CONSTRAINT FK_F1E1D6DEF92F3E70 FOREIGN KEY (country_id) REFERENCES mod_geo_country (id)');
        $this->addSql(
            'ALTER TABLE mod_geo_state_translation ADD CONSTRAINT FK_E11E820D5D83CC1 FOREIGN KEY (state_id) REFERENCES mod_geo_state (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_geo_city DROP FOREIGN KEY FK_37BD1187F92F3E70');
        $this->addSql('ALTER TABLE mod_geo_city DROP FOREIGN KEY FK_37BD11875D83CC1');
        $this->addSql('ALTER TABLE mod_geo_city_translation DROP FOREIGN KEY FK_A0ABC7678BAC62AF');
        $this->addSql('ALTER TABLE mod_geo_country_translation DROP FOREIGN KEY FK_C0236809F92F3E70');
        $this->addSql('ALTER TABLE mod_geo_district DROP FOREIGN KEY FK_D52005CA8BAC62AF');
        $this->addSql('ALTER TABLE mod_geo_district_translation DROP FOREIGN KEY FK_30DD6C04B08FA272');
        $this->addSql('ALTER TABLE mod_geo_state DROP FOREIGN KEY FK_F1E1D6DEF92F3E70');
        $this->addSql('ALTER TABLE mod_geo_state_translation DROP FOREIGN KEY FK_E11E820D5D83CC1');
        $this->addSql('DROP TABLE mod_geo_city');
        $this->addSql('DROP TABLE mod_geo_city_translation');
        $this->addSql('DROP TABLE mod_geo_country');
        $this->addSql('DROP TABLE mod_geo_country_translation');
        $this->addSql('DROP TABLE mod_geo_district');
        $this->addSql('DROP TABLE mod_geo_district_translation');
        $this->addSql('DROP TABLE mod_geo_state');
        $this->addSql('DROP TABLE mod_geo_state_translation');
    }
}
