<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250418100721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE "permission" (id SERIAL NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, category VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_PERMISSION_NAME ON "permission" (name)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "permission".created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE "refresh_token" (token VARCHAR(255) NOT NULL, user_id INT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, PRIMARY KEY(token))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C74F2195A76ED395 ON "refresh_token" (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_refresh_token_expires ON "refresh_token" (expires_at)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "refresh_token".expires_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "refresh_token".created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE "role" (id SERIAL NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_57698A6A5E237E06 ON "role" (name)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "role".created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_roles (role_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(role_id, user_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_54FCD59FD60322AC ON user_roles (role_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_54FCD59FA76ED395 ON user_roles (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE role_permissions (role_id INT NOT NULL, permission_id INT NOT NULL, PRIMARY KEY(role_id, permission_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1FBA94E6D60322AC ON role_permissions (role_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1FBA94E6FED90CCA ON role_permissions (permission_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (id SERIAL NOT NULL, username VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, last_login TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON "user" (username)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "user".last_login IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "user".created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "refresh_token" ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES "role" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6D60322AC FOREIGN KEY (role_id) REFERENCES "role" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6FED90CCA FOREIGN KEY (permission_id) REFERENCES "permission" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "refresh_token" DROP CONSTRAINT FK_C74F2195A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FD60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions DROP CONSTRAINT FK_1FBA94E6D60322AC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE role_permissions DROP CONSTRAINT FK_1FBA94E6FED90CCA
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "permission"
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "refresh_token"
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "role"
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_roles
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE role_permissions
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "user"
        SQL);
    }
}
