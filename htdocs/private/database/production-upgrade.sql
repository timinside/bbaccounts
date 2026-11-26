-- bbaccounts production upgrade: all pending migrations, in order.
-- Safe to re-run on MariaDB (uses IF NOT EXISTS/IF EXISTS where supported).
-- Apply to a database that already has the base schema.
-- Source: private/database/migrations/ (2026-11-14 .. 2026-12-09).

-- Public profile description, tester beta flags, notification icons/links, emoji presets.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS description VARCHAR(280) NULL AFTER display_name,
    ADD COLUMN IF NOT EXISTS beta_features VARCHAR(190) NULL AFTER is_tester;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS icon VARCHAR(30) NULL AFTER body,
    ADD COLUMN IF NOT EXISTS link_text VARCHAR(60) NULL AFTER icon,
    ADD COLUMN IF NOT EXISTS link_url VARCHAR(500) NULL AFTER link_text;

-- Old preset avatars were bundled PNGs numbered 1-12. They are now system emoji.
UPDATE accounts SET avatar_value = CASE avatar_value
    WHEN '1' THEN '😀'
    WHEN '2' THEN '😎'
    WHEN '3' THEN '🦊'
    WHEN '4' THEN '🐱'
    WHEN '5' THEN '🐼'
    WHEN '6' THEN '🚀'
    WHEN '7' THEN '🌙'
    WHEN '8' THEN '⚡'
    WHEN '9' THEN '🎮'
    WHEN '10' THEN '🎧'
    WHEN '11' THEN '🌸'
    WHEN '12' THEN '🍀'
    ELSE avatar_value
END
WHERE avatar_type = 'preset' AND avatar_value REGEXP '^(1|2|3|4|5|6|7|8|9|10|11|12)$';


-- Per-provider opt-in for exposing linked usernames through /oauth/userinfo.

ALTER TABLE auth_identities
    ADD COLUMN IF NOT EXISTS is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER username;


-- Profile handles, role visibility, custom links, badges, and showcases.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS username VARCHAR(30) NULL AFTER display_name,
    ADD COLUMN IF NOT EXISTS show_role_admin TINYINT(1) NOT NULL DEFAULT 1 AFTER is_tester,
    ADD COLUMN IF NOT EXISTS show_role_tester TINYINT(1) NOT NULL DEFAULT 1 AFTER show_role_admin,
    ADD COLUMN IF NOT EXISTS show_role_developer TINYINT(1) NOT NULL DEFAULT 1 AFTER show_role_tester;

-- Give existing accounts a stable handle; everyone can change it in Profile.
UPDATE accounts SET username = LOWER(CONCAT(
    CASE WHEN CHAR_LENGTH(REGEXP_REPLACE(COALESCE(NULLIF(display_name, ''), SUBSTRING_INDEX(email, '@', 1)), '[^a-zA-Z0-9_]', '')) >= 3
         THEN LEFT(REGEXP_REPLACE(COALESCE(NULLIF(display_name, ''), SUBSTRING_INDEX(email, '@', 1)), '[^a-zA-Z0-9_]', ''), 24)
         ELSE 'user' END,
    id))
WHERE username IS NULL OR username = '';

-- unique key may already exist; ignore error #1061 if so
CREATE UNIQUE INDEX IF NOT EXISTS uq_accounts_username ON accounts (username);

CREATE TABLE IF NOT EXISTS account_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(40) NOT NULL,
    url VARCHAR(300) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_links_account (account_id, id),
    CONSTRAINT fk_links_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS badges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(40) NOT NULL,
    name VARCHAR(60) NOT NULL,
    description VARCHAR(160) NULL,
    icon VARCHAR(30) NULL,
    style VARCHAR(20) NOT NULL DEFAULT 'badge',
    rule VARCHAR(30) NOT NULL DEFAULT 'manual',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_badges_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO badges (slug, name, description, icon, style, rule) VALUES
    ('project-owner', 'Project Owner', 'Created and maintains at least one project.', 'code', 'badge-ok', 'project_owner'),
    ('veteran', 'Veteran', 'Has been a member for at least 30 days.', 'shield', 'badge', 'account_30d'),
    ('contributor', 'Contributor', 'Helped improve bbaccounts.', 'user', 'badge', 'manual'),
    ('bug-hunter', 'Bug Hunter', 'Reported a bug that was fixed.', 'alert', 'badge-warn', 'manual')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS account_badges (
    account_id BIGINT UNSIGNED NOT NULL,
    badge_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id, badge_id),
    KEY idx_account_badges_badge (badge_id),
    CONSTRAINT fk_account_badges_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_account_badges_badge FOREIGN KEY (badge_id) REFERENCES badges (id) ON DELETE CASCADE,
    CONSTRAINT fk_account_badges_actor FOREIGN KEY (assigned_by) REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS showcases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'text',
    title VARCHAR(60) NOT NULL,
    body VARCHAR(500) NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_showcases_account (account_id, id),
    CONSTRAINT fk_showcases_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Profile banners, rich showcase data, and comments on profiles and projects.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS profile_banner VARCHAR(255) NULL AFTER avatar_color,
    ADD COLUMN IF NOT EXISTS profile_banner_color CHAR(7) NULL AFTER profile_banner;

ALTER TABLE showcases
    MODIFY COLUMN body TEXT NULL,
    ADD COLUMN IF NOT EXISTS meta TEXT NULL AFTER body;

CREATE TABLE IF NOT EXISTS comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_id BIGINT UNSIGNED NOT NULL,
    target_type ENUM('profile', 'project') NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    body VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_target (target_type, target_id, id),
    KEY idx_comments_author (author_id, id),
    CONSTRAINT fk_comments_author FOREIGN KEY (author_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Profile likes, points, and purchasable showcase slots.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS points INT NOT NULL DEFAULT 0 AFTER is_tester,
    ADD COLUMN IF NOT EXISTS showcase_slots TINYINT NOT NULL DEFAULT 3 AFTER points;

CREATE TABLE IF NOT EXISTS profile_likes (
    account_id BIGINT UNSIGNED NOT NULL,
    profile_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id, profile_id),
    KEY idx_profile_likes_profile (profile_id),
    CONSTRAINT fk_profile_likes_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_profile_likes_profile FOREIGN KEY (profile_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Profile contact methods (Telegram, Discord, WhatsApp, up to two emails, other).

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS contacts TEXT NULL AFTER profile_banner_color;


-- Stable per-browser identifier so Security can list every account signed in on this device.

ALTER TABLE auth_sessions
    ADD COLUMN IF NOT EXISTS device_id CHAR(32) NULL AFTER user_agent;
CREATE INDEX IF NOT EXISTS idx_auth_sessions_device ON auth_sessions (device_id, revoked_at, expires_at);


-- Friends and a profile background image.

CREATE TABLE IF NOT EXISTS friendships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requester_id BIGINT UNSIGNED NOT NULL,
    addressee_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_friendships_pair (requester_id, addressee_id),
    KEY idx_friendships_addressee (addressee_id, status),
    KEY idx_friendships_requester (requester_id, status),
    CONSTRAINT fk_friendships_requester FOREIGN KEY (requester_id) REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_friendships_addressee FOREIGN KEY (addressee_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS profile_background VARCHAR(120) NULL AFTER profile_banner_color;


-- Key/value settings, starting with the home page side banners.

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(64) NOT NULL,
    value TEXT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Profile comment reactions.

CREATE TABLE IF NOT EXISTS comment_reactions (
    comment_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, account_id),
    KEY idx_comment_reactions_comment (comment_id),
    CONSTRAINT fk_comment_reactions_comment FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_reactions_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM comment_reactions WHERE kind NOT IN ('up', 'down');


-- One level of comment replies, nested under their parent comment.

ALTER TABLE comments ADD COLUMN IF NOT EXISTS parent_id BIGINT UNSIGNED NULL AFTER target_id;
CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments (parent_id);
-- fk_comments_parent: add manually if missing (constraint names can't use IF NOT EXISTS on all servers)


-- Moderator role, reported comments, and admin-issued verification checks.

ALTER TABLE accounts ADD COLUMN IF NOT EXISTS is_moderator TINYINT(1) NOT NULL DEFAULT 0 AFTER is_tester;

CREATE TABLE IF NOT EXISTS comment_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    reporter_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(500) NULL,
    status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comment_reports (comment_id, reporter_id),
    KEY idx_comment_reports_status (status, id),
    CONSTRAINT fk_comment_reports_comment FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_reports_reporter FOREIGN KEY (reporter_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type ENUM('profile', 'project') NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    kind ENUM('gold', 'blue') NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    granted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_verifications_target (target_type, target_id),
    CONSTRAINT fk_verifications_granted_by FOREIGN KEY (granted_by) REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- Moderator role visibility and buyable friend capacity.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS show_role_moderator TINYINT(1) NOT NULL DEFAULT 1 AFTER show_role_developer,
    ADD COLUMN IF NOT EXISTS friend_limit TINYINT UNSIGNED NOT NULL DEFAULT 20 AFTER showcase_slots;


-- Give existing accounts the same two default showcases new accounts get.
INSERT INTO showcases (account_id, type, title, is_visible)
SELECT a.id, d.type, d.title, 1
FROM accounts a
CROSS JOIN (
    SELECT 'badges' AS type, 'Badge Earned' AS title
    UNION ALL SELECT 'friends', 'Friends'
) d
WHERE (a.status IS NULL OR a.status <> 'deleted')
  AND NOT EXISTS (
      SELECT 1 FROM showcases s WHERE s.account_id = a.id AND s.type = d.type
  );


-- Move legacy profile links into a Links showcase and stop showing them separately.
INSERT INTO showcases (account_id, type, title, body, is_visible)
SELECT l.account_id, 'links', 'Links',
       GROUP_CONCAT(CONCAT(l.url, ' | ', l.label) ORDER BY l.id SEPARATOR '\n'), 1
FROM account_links l
WHERE NOT EXISTS (
    SELECT 1 FROM showcases s WHERE s.account_id = l.account_id AND s.type = 'links'
)
GROUP BY l.account_id;


-- Separate blur strengths for the public profile banner and page background (0 = off).
-- Showcase width and Project reviews live in the existing showcases.meta JSON column.
ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS profile_banner_blur TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER profile_background,
    ADD COLUMN IF NOT EXISTS profile_background_blur TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER profile_banner_blur;


-- Referral links: created by the account owner for a limited number of days.
-- An account starts with one slot; extra slots cost points and hold one link each.
ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS referred_by BIGINT UNSIGNED NULL AFTER purge_after,
    ADD COLUMN IF NOT EXISTS referral_slots TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER friend_limit;
CREATE INDEX IF NOT EXISTS idx_accounts_referred_by ON accounts (referred_by);
-- fk_accounts_referred_by: add manually if missing

CREATE TABLE IF NOT EXISTS referrals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    points_owner INT NOT NULL DEFAULT 10,
    points_new INT NOT NULL DEFAULT 5,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    signups INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_referrals_code (code),
    KEY idx_referrals_account (account_id),
    CONSTRAINT fk_referrals_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Public admin warning note shown next to the account name.
ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS warning_text VARCHAR(255) NULL AFTER description;

-- Moderator tickets: tester applications, deletion requests, and support chats.
CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    type ENUM('tester', 'deletion', 'help') NOT NULL,
    status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_tickets_type_status (type, status, id),
    KEY idx_tickets_account (account_id, id),
    CONSTRAINT fk_tickets_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_messages_ticket (ticket_id, id),
    CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_messages_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- bbaccounts levels, XP and private profiles.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS xp INT UNSIGNED NOT NULL DEFAULT 0 AFTER points,
    ADD COLUMN IF NOT EXISTS level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER xp,
    ADD COLUMN IF NOT EXISTS private_profile TINYINT(1) NOT NULL DEFAULT 0 AFTER share_social;


-- Project-owned badges and teams (Discord-style organisation).

ALTER TABLE badges ADD COLUMN IF NOT EXISTS project_id BIGINT UNSIGNED NULL AFTER rule;
CREATE INDEX IF NOT EXISTS idx_badges_project ON badges (project_id);
-- fk_badges_project: add manually if missing

ALTER TABLE projects ADD COLUMN IF NOT EXISTS team_id BIGINT UNSIGNED NULL AFTER owner_id;
CREATE INDEX IF NOT EXISTS idx_projects_team ON projects (team_id);

CREATE TABLE IF NOT EXISTS teams (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(64) NOT NULL,
    description VARCHAR(280) NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_slug (slug),
    KEY idx_teams_owner (owner_id),
    CONSTRAINT fk_teams_owner FOREIGN KEY (owner_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
    team_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    role ENUM('Owner', 'Co-owner', 'Member') NOT NULL DEFAULT 'Member',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, account_id),
    KEY idx_team_members_account (account_id),
    CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_team_members_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_subscriptions (
    team_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, account_id),
    KEY idx_team_subscriptions_account (account_id),
    CONSTRAINT fk_team_subscriptions_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_team_subscriptions_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fk_projects_team: add manually if missing (requires teams table)


-- Role application tickets and the profile editor toggle (v1 stays the default).

ALTER TABLE tickets
    MODIFY COLUMN type ENUM('tester', 'developer', 'admin', 'deletion', 'help') NOT NULL;

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS profile_editor ENUM('classic', 'studio') NOT NULL DEFAULT 'classic' AFTER private_profile;


-- Point and XP reward rebalance. Existing referral links keep their stored rewards.

ALTER TABLE referrals
    MODIFY COLUMN points_owner INT NOT NULL DEFAULT 25,
    MODIFY COLUMN points_new INT NOT NULL DEFAULT 10;


-- Stable release alerts for bbaccounts Auth and the corrected banner promise.

CREATE TABLE IF NOT EXISTS auth_release_subscribers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_release_subscribers_email (email),
    KEY idx_auth_release_subscribers_account (account_id),
    CONSTRAINT fk_auth_release_subscribers_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE banners
SET body = 'Report bugs or submit feature PRs to earn a Bug Hunter badge.'
WHERE body = 'Report bugs or submit feature PRs to earn a Beta Contributor badge and unlock exclusive features.';


-- Drag-and-drop showcase order.

ALTER TABLE showcases ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER is_visible;

UPDATE showcases SET sort_order = id WHERE sort_order = 0;


-- Team announcements for members and followers.

CREATE TABLE IF NOT EXISTS team_announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NULL,
    subject VARCHAR(120) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_team_announcements_team (team_id, id),
    CONSTRAINT fk_team_announcements_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_team_announcements_author FOREIGN KEY (author_id) REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- bbaccounts Auth Guard: TOTP secret and replay protection.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS guard_secret VARCHAR(255) NULL AFTER password_set,
    ADD COLUMN IF NOT EXISTS guard_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER guard_secret,
    ADD COLUMN IF NOT EXISTS guard_last_step BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER guard_enabled;


-- One "Teams" showcase per account instead of a "Team Members" showcase per team.

UPDATE showcases
SET title = 'Teams',
    meta = JSON_SET(IF(JSON_VALID(meta), meta, '{}'), '$.include_subscribed', 0)
WHERE type = 'team';

DELETE s FROM showcases s
JOIN (
    SELECT account_id, MIN(id) AS keep_id
    FROM showcases
    WHERE type = 'team'
    GROUP BY account_id
) k ON k.account_id = s.account_id
WHERE s.type = 'team' AND s.id > k.keep_id;


-- Keep verifications when the granting account is deleted: granted_by becomes NULL.

ALTER TABLE verifications DROP FOREIGN KEY IF EXISTS fk_verifications_granted_by;
ALTER TABLE verifications MODIFY COLUMN granted_by BIGINT UNSIGNED NULL;
ALTER TABLE verifications ADD CONSTRAINT fk_verifications_granted_by FOREIGN KEY (granted_by) REFERENCES accounts (id) ON DELETE SET NULL;


-- In-app waitlist: notify subscribed accounts when bbaccounts Auth releases.

CREATE TABLE IF NOT EXISTS auth_release_waitlist (
    account_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id),
    CONSTRAINT fk_auth_release_waitlist_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Telegram 2FA and notification binding.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(32) NULL AFTER guard_last_step,
    ADD COLUMN IF NOT EXISTS telegram_username VARCHAR(64) NULL AFTER telegram_chat_id,
    ADD COLUMN IF NOT EXISTS telegram_2fa TINYINT(1) NOT NULL DEFAULT 0 AFTER telegram_username,
    ADD COLUMN IF NOT EXISTS telegram_notify_login TINYINT(1) NOT NULL DEFAULT 1 AFTER telegram_2fa,
    ADD COLUMN IF NOT EXISTS telegram_notify_security TINYINT(1) NOT NULL DEFAULT 1 AFTER telegram_notify_login,
    ADD COLUMN IF NOT EXISTS telegram_login_code VARCHAR(64) NULL AFTER telegram_notify_security,
    ADD COLUMN IF NOT EXISTS telegram_login_expires_at DATETIME NULL AFTER telegram_login_code,
    ADD COLUMN IF NOT EXISTS telegram_link_code VARCHAR(32) NULL AFTER telegram_login_expires_at,
    ADD COLUMN IF NOT EXISTS telegram_link_expires_at DATETIME NULL AFTER telegram_link_code;

-- Profile card/showcase transparency.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS profile_card_opacity TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER profile_background_blur;
-- Transparent project icon background.

ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS icon_transparent TINYINT(1) NOT NULL DEFAULT 0 AFTER icon_approved;

-- Profile banner disable and card blur toggle.

ALTER TABLE accounts
    ADD COLUMN IF NOT EXISTS profile_banner_disabled TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_banner_blur,
    ADD COLUMN IF NOT EXISTS profile_card_blur TINYINT(1) NOT NULL DEFAULT 1 AFTER profile_card_opacity;

-- Project storage cells (verified projects, optional user binding).

CREATE TABLE IF NOT EXISTS project_cells (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cell_key VARCHAR(120) NOT NULL,
    cell_value MEDIUMTEXT NOT NULL,
    value_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_cells_scope_key (project_id, account_id, cell_key),
    KEY idx_project_cells_project (project_id, updated_at),
    KEY idx_project_cells_project_account (project_id, account_id, updated_at),
    CONSTRAINT fk_project_cells_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
