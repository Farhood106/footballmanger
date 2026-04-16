-- database/schema.sql

CREATE DATABASE IF NOT EXISTS football_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE football_manager;

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('MANAGER', 'ADMIN') DEFAULT 'MANAGER',
    game_role ENUM('COACH', 'OWNER') DEFAULT 'COACH',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- Sessions
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- Clubs
CREATE TABLE clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    owner_user_id INT UNIQUE,
    manager_user_id INT UNIQUE,
    name VARCHAR(255) UNIQUE NOT NULL,
    short_name VARCHAR(10) NOT NULL,
    country VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    founded INT NOT NULL,
    reputation INT DEFAULT 50,
    balance BIGINT DEFAULT 10000000,
    stadium_name VARCHAR(255) NOT NULL,
    stadium_capacity INT DEFAULT 30000,
    badge_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_owner (owner_user_id),
    INDEX idx_manager (manager_user_id)
) ENGINE=InnoDB;

-- Club Ownership Requests
CREATE TABLE club_ownership_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    club_id INT NOT NULL,
    offer_amount BIGINT DEFAULT 0,
    message TEXT,
    status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME,
    reviewed_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request_status (status),
    UNIQUE KEY unique_pending_request (user_id, club_id, status)
) ENGINE=InnoDB;

-- Manager Expectations
CREATE TABLE club_manager_expectations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    owner_user_id INT,
    title VARCHAR(255) NOT NULL,
    expectations TEXT,
    duties TEXT,
    commitments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_club_expectation (club_id),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Manager Applications
CREATE TABLE club_manager_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    coach_user_id INT NOT NULL,
    proposed_expectations TEXT,
    proposed_duties TEXT,
    proposed_commitments TEXT,
    cover_letter TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason VARCHAR(1000),
    reviewed_by_user_id INT,
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_pending_coach_club (club_id, coach_user_id, status)
) ENGINE=InnoDB;

-- Players
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_key VARCHAR(100) NULL,
    club_id INT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    nationality VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    position ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LW','RW','ST','CF') NOT NULL,
    preferred_foot ENUM('LEFT','RIGHT','BOTH') DEFAULT 'RIGHT',

    pace INT NOT NULL,
    shooting INT NOT NULL,
    passing INT NOT NULL,
    dribbling INT NOT NULL,
    defending INT NOT NULL,
    physical INT NOT NULL,

    overall INT NOT NULL,
    potential INT NOT NULL,
    form DECIMAL(3,1) DEFAULT 6.5,
    fatigue INT DEFAULT 0,
    morale DECIMAL(3,1) DEFAULT 7.0,
    fitness INT DEFAULT 100,
    morale_score INT DEFAULT 70,
    squad_role ENUM('KEY_PLAYER','REGULAR_STARTER','ROTATION','BENCH','PROSPECT') DEFAULT 'ROTATION',
    last_played_at DATETIME NULL,
    last_minutes_played INT DEFAULT 0,

    wage INT DEFAULT 0,
    contract_end DATE,
    market_value BIGINT DEFAULT 0,
    is_transfer_listed BOOLEAN DEFAULT 0,
    asking_price BIGINT DEFAULT NULL,
    transfer_listed_at DATETIME NULL,

    is_injured BOOLEAN DEFAULT FALSE,
    injury_days INT DEFAULT 0,
    is_on_loan BOOLEAN DEFAULT FALSE,
    is_retired BOOLEAN DEFAULT FALSE,
    is_academy_origin BOOLEAN DEFAULT 0,
    academy_origin_club_id INT NULL,
    academy_intake_season_id INT NULL,
    academy_intake_batch_key VARCHAR(64) NULL,

    growth_rate DECIMAL(3,2) DEFAULT 1.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE SET NULL,
    FOREIGN KEY (academy_origin_club_id) REFERENCES clubs(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_player_external_key (external_key),
    INDEX idx_club (club_id),
    INDEX idx_position (position),
    INDEX idx_overall (overall)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS youth_intake_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    intake_season_id INT NOT NULL,
    intake_key VARCHAR(64) NOT NULL,
    academy_level INT NOT NULL DEFAULT 1,
    generated_count INT NOT NULL DEFAULT 0,
    generated_player_ids_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_season_intake (club_id, intake_season_id, intake_key),
    INDEX idx_intake_club_created (club_id, created_at),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Abilities
CREATE TABLE abilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('INNATE','ACQUIRED') NOT NULL,
    category ENUM('ATTACKING','DEFENDING','MENTAL','PHYSICAL','LEADERSHIP') NOT NULL,
    unlock_condition JSON,
    INDEX idx_code (code)
) ENGINE=InnoDB;

-- Player Abilities
CREATE TABLE player_abilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    ability_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_ability (player_id, ability_id)
) ENGINE=InnoDB;

-- Competitions
CREATE TABLE competitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_competition_id INT,
    code VARCHAR(50),
    name VARCHAR(255) NOT NULL,
    type ENUM('LEAGUE','CUP','SUPER_CUP','CHAMPIONS_LEAGUE','FRIENDLY') NOT NULL,
    country VARCHAR(100),
    level INT DEFAULT 1,
    teams_count INT DEFAULT 20,
    promotion_slots INT DEFAULT 0,
    relegation_slots INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_competition_id) REFERENCES competitions(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_competition_code (code)
) ENGINE=InnoDB;

-- Seasons
CREATE TABLE seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('UPCOMING','ACTIVE','FINISHED') DEFAULT 'UPCOMING',
    current_week INT DEFAULT 0,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE players
    ADD CONSTRAINT fk_players_academy_intake_season
    FOREIGN KEY (academy_intake_season_id) REFERENCES seasons(id) ON DELETE SET NULL;

ALTER TABLE youth_intake_logs
    ADD CONSTRAINT fk_youth_intake_logs_intake_season
    FOREIGN KEY (intake_season_id) REFERENCES seasons(id) ON DELETE CASCADE;

-- Club Seasons
CREATE TABLE club_seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    season_id INT NOT NULL,
    entry_type ENUM('direct','promoted','relegated','champion','qualified','wildcard') NOT NULL DEFAULT 'direct',
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_club_season (club_id, season_id),
    INDEX idx_season_entry_type (season_id, entry_type)
) ENGINE=InnoDB;

-- Standings
CREATE TABLE standings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    club_id INT NOT NULL,
    position INT DEFAULT 0,
    played INT DEFAULT 0,
    won INT DEFAULT 0,
    drawn INT DEFAULT 0,
    lost INT DEFAULT 0,
    goals_for INT DEFAULT 0,
    goals_against INT DEFAULT 0,
    goal_diff INT DEFAULT 0,
    points INT DEFAULT 0,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_season_club (season_id, club_id),
    INDEX idx_position (season_id, position)
) ENGINE=InnoDB;



-- Season rollover logs for finalized standings and applied carryover plans
CREATE TABLE IF NOT EXISTS season_rollover_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    competition_id INT NOT NULL,
    status ENUM('FINALIZED','APPLIED') DEFAULT 'FINALIZED',
    finalized_standings_json JSON,
    rollover_plan_json JSON,
    finalized_at DATETIME NOT NULL,
    applied_at DATETIME,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_season_rollover (season_id)
) ENGINE=InnoDB;

-- Cross-league qualification slot mappings into champions competitions
CREATE TABLE IF NOT EXISTS competition_qualification_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_competition_id INT NOT NULL,
    target_competition_id INT NOT NULL,
    slots INT NOT NULL DEFAULT 1,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_source_target (source_competition_id, target_competition_id),
    INDEX idx_target_active (target_competition_id, is_active),
    FOREIGN KEY (source_competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (target_competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Matches
CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    home_club_id INT NOT NULL,
    away_club_id INT NOT NULL,
    week INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status ENUM('SCHEDULED','LIVE','FINISHED','POSTPONED','CANCELLED') DEFAULT 'SCHEDULED',

    home_score INT,
    away_score INT,

    stats JSON,
    home_tactics JSON,
    away_tactics JSON,

    home_xg DECIMAL(4,2),
    away_xg DECIMAL(4,2),

    played_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (home_club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (away_club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_schedule (scheduled_at, status),
    INDEX idx_week (season_id, week)
) ENGINE=InnoDB;

-- Match Lineups
CREATE TABLE match_lineups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    club_id INT NOT NULL,
    player_id INT NOT NULL,
    position ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LW','RW','ST','CF') NOT NULL,
    is_starter BOOLEAN DEFAULT TRUE,
    shirt_number INT NOT NULL,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_player (match_id, player_id)
) ENGINE=InnoDB;

-- Match Events
CREATE TABLE match_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    minute INT NOT NULL,
    type ENUM('GOAL','OWN_GOAL','YELLOW_CARD','RED_CARD','SECOND_YELLOW','SUBSTITUTION','INJURY','PENALTY_SCORED','PENALTY_MISSED') NOT NULL,
    team ENUM('HOME','AWAY') NOT NULL,
    player_id INT,
    assist_player_id INT,
    details JSON,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (assist_player_id) REFERENCES players(id) ON DELETE SET NULL,
    INDEX idx_match (match_id)
) ENGINE=InnoDB;

-- Player Match Ratings
CREATE TABLE player_match_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_id INT NOT NULL,
    rating DECIMAL(3,1) NOT NULL,
    goals INT DEFAULT 0,
    assists INT DEFAULT 0,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_player_rating (match_id, player_id)
) ENGINE=InnoDB;

-- Tactics
CREATE TABLE tactics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT UNIQUE NOT NULL,
    formation VARCHAR(10) DEFAULT '4-3-3',
    style ENUM('ATTACKING','DEFENSIVE','COUNTER','PRESSING','BALANCED','POSSESSION') DEFAULT 'BALANCED',
    mentality ENUM('AGGRESSIVE','NORMAL','CAUTIOUS') DEFAULT 'NORMAL',
    pressing INT DEFAULT 5,
    tempo INT DEFAULT 5,
    width INT DEFAULT 5,

    corner_taker INT,
    freekick_taker INT,
    penalty_taker INT,
    captain INT,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (corner_taker) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (freekick_taker) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (penalty_taker) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (captain) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Transfers
CREATE TABLE transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    from_club_id INT,
    to_club_id INT,
    type ENUM('PERMANENT','LOAN','FREE','YOUTH_PROMOTION') NOT NULL,
    fee BIGINT DEFAULT 0,
    counter_fee BIGINT DEFAULT NULL,
    negotiation_round TINYINT DEFAULT 0,
    status ENUM('PENDING','COUNTERED','COMPLETED','CANCELLED','REJECTED','SUPERSEDED') DEFAULT 'PENDING',
    initiated_by INT NOT NULL,
    season_id INT,
    loan_end DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    countered_at DATETIME NULL,
    responded_at DATETIME NULL,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (from_club_id) REFERENCES clubs(id) ON DELETE SET NULL,
    FOREIGN KEY (to_club_id) REFERENCES clubs(id) ON DELETE SET NULL,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
    INDEX idx_transfer_player_status (player_id, status),
    INDEX idx_transfer_seller_status (from_club_id, status),
    INDEX idx_transfer_buyer_status (to_club_id, status)
) ENGINE=InnoDB;

-- Player Season Stats
CREATE TABLE player_season_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    season_id INT NOT NULL,
    club_id INT NOT NULL,
    appearances INT DEFAULT 0,
    starts INT DEFAULT 0,
    minutes_played INT DEFAULT 0,
    goals INT DEFAULT 0,
    assists INT DEFAULT 0,
    yellow_cards INT DEFAULT 0,
    red_cards INT DEFAULT 0,
    avg_rating DECIMAL(3,1) DEFAULT 0,
    clean_sheets INT DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_season_club (player_id, season_id, club_id)
) ENGINE=InnoDB;

-- Player career history (season-by-season per club)
CREATE TABLE IF NOT EXISTS player_career_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    season_id INT NOT NULL,
    club_id INT NOT NULL,
    appearances INT DEFAULT 0,
    starts INT DEFAULT 0,
    minutes_played INT DEFAULT 0,
    goals INT DEFAULT 0,
    assists INT DEFAULT 0,
    yellow_cards INT DEFAULT 0,
    red_cards INT DEFAULT 0,
    avg_rating DECIMAL(3,1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_player_season_club_history (player_id, season_id, club_id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Injuries
CREATE TABLE injuries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    type ENUM('MUSCLE','KNEE','ANKLE','HAMSTRING','BACK','SHOULDER','CONCUSSION') NOT NULL,
    severity INT NOT NULL,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recovered_at DATETIME,
    match_id INT,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Finances
CREATE TABLE finances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    type ENUM('TRANSFER_IN','TRANSFER_OUT','WAGE','TICKET_REVENUE','SPONSOR','PRIZE_MONEY','LOAN_FEE','YOUTH_SALE','STAFF_WAGE','FACILITY_COST') NOT NULL,
    amount BIGINT NOT NULL,
    description VARCHAR(500),
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    season_id INT,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
    INDEX idx_club_date (club_id, date)
) ENGINE=InnoDB;

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('MATCH_RESULT','TRANSFER_OFFER','INJURY','CONTRACT_EXPIRY','ABILITY_UNLOCKED','SEASON_END','PROMOTION','RELEGATION') NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- Tactical lineups by match phase (single active tactical setup, multiple saved phase lineups)
CREATE TABLE IF NOT EXISTS tactic_lineups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    phase_key ENUM('MATCH_1','MATCH_2','RECOVERY','NEXT_DAY') DEFAULT 'MATCH_1',
    player_id INT NOT NULL,
    position_slot ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LW','RW','ST','CF') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_lineup_slot (club_id, phase_key, position_slot, is_active),
    INDEX idx_club_phase (club_id, phase_key)
) ENGINE=InnoDB;

-- Contracts between owners and coaches
CREATE TABLE IF NOT EXISTS manager_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    owner_user_id INT NOT NULL,
    coach_user_id INT NOT NULL,
    status ENUM('PROPOSED','ACTIVE','TERMINATED','EXPIRED','REJECTED') DEFAULT 'PROPOSED',
    start_date DATE,
    end_date DATE,
    salary BIGINT DEFAULT 0,
    terms_json JSON,
    termination_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_contract_status (club_id, status)
) ENGINE=InnoDB;

-- Negotiation offers for owner/coach contract discussions
CREATE TABLE IF NOT EXISTS manager_contract_negotiations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    club_id INT NOT NULL,
    coach_user_id INT NOT NULL,
    owner_user_id INT NOT NULL,
    status ENUM('open','accepted','rejected','expired','superseded') DEFAULT 'open',
    offered_salary_per_cycle BIGINT NOT NULL,
    offered_contract_length_cycles INT NOT NULL,
    club_objective VARCHAR(255),
    bonus_promotion BIGINT DEFAULT 0,
    bonus_title BIGINT DEFAULT 0,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME,
    FOREIGN KEY (application_id) REFERENCES club_manager_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_negotiation_application_status (application_id, status),
    INDEX idx_negotiation_coach_status (coach_user_id, status),
    INDEX idx_negotiation_owner_status (owner_user_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS manager_contract_terminations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    club_id INT NOT NULL,
    owner_user_id INT NULL,
    coach_user_id INT NULL,
    terminated_by_user_id INT NOT NULL,
    termination_type ENUM('OWNER_TERMINATION','MUTUAL_TERMINATION','ADMIN_FORCED_TERMINATION') NOT NULL,
    compensation_amount BIGINT DEFAULT 0,
    reason VARCHAR(1000),
    governance_case_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_termination_contract (contract_id, created_at),
    INDEX idx_termination_club (club_id, created_at),
    FOREIGN KEY (contract_id) REFERENCES manager_contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (terminated_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Governance: owner/coach dispute case
CREATE TABLE IF NOT EXISTS club_governance_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    contract_id INT,
    owner_user_id INT,
    manager_user_id INT,
    raised_by_user_id INT NOT NULL,
    against_user_id INT,
    case_type ENUM('UNFAIR_DISMISSAL','COMPENSATION_DISAGREEMENT','CONTRACT_BREACH','MUTUAL_TERMINATION_DISPUTE','OTHER') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open','under_review','resolved','rejected') DEFAULT 'open',
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES manager_contracts(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (raised_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (against_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_governance_status (club_id, status)
) ENGINE=InnoDB;

ALTER TABLE manager_contract_terminations
    ADD CONSTRAINT fk_manager_contract_terminations_governance_case
    FOREIGN KEY (governance_case_id) REFERENCES club_governance_cases(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS club_governance_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    decision_type ENUM('CASE_UPHELD','CASE_REJECTED','WARNING','PENALTY','COMPENSATION','MIXED') NOT NULL,
    decision_summary TEXT NOT NULL,
    penalty_amount BIGINT DEFAULT 0,
    compensation_amount BIGINT DEFAULT 0,
    decided_by_user_id INT,
    decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES club_governance_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_case_decision (case_id, decided_at)
) ENGINE=InnoDB;

-- Club finance ledger (authoritative transaction history)
CREATE TABLE IF NOT EXISTS club_finance_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    season_id INT,
    entry_type ENUM('COACH_SALARY','MATCH_REWARD','SEASON_REWARD','GOVERNANCE_PENALTY','GOVERNANCE_COMPENSATION','TRANSFER_IN','TRANSFER_OUT','OWNER_FUNDING','SPONSOR_INCOME','MANUAL_ADMIN_ADJUSTMENT','FACILITY_UPGRADE','FACILITY_DOWNGRADE_REFUND','FACILITY_MAINTENANCE','MANAGER_TERMINATION_COMPENSATION','WAGE','STAFF_WAGE','SPONSOR','TICKET','PRIZE','PENALTY','OTHER') NOT NULL,
    amount BIGINT NOT NULL,
    description VARCHAR(500),
    reference_type VARCHAR(50),
    reference_id INT,
    meta_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
    INDEX idx_finance_club_date (club_id, created_at)
) ENGINE=InnoDB;



-- Club sponsors (sponsorship-ready foundation)
CREATE TABLE IF NOT EXISTS club_sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    tier ENUM('main','secondary','minor') DEFAULT 'minor',
    brand_name VARCHAR(255) NOT NULL,
    description TEXT,
    contact_link VARCHAR(500),
    banner_url VARCHAR(500),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_club_sponsor_tier (club_id, tier)
) ENGINE=InnoDB;

-- Club facility slots (infrastructure progression foundation)
CREATE TABLE IF NOT EXISTS club_facilities (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    facility_type ENUM('stadium','training_ground','youth_academy','headquarters') NOT NULL,
    level INT NOT NULL DEFAULT 1,
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_facility_type (club_id, facility_type),
    INDEX idx_club_facility_level (club_id, facility_type, level),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Owner funding events (payment-verification-ready)
CREATE TABLE IF NOT EXISTS club_owner_funding_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    owner_user_id INT NOT NULL,
    amount BIGINT NOT NULL,
    note VARCHAR(500),
    external_reference VARCHAR(255),
    status ENUM('posted','pending','rejected') DEFAULT 'posted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner_funding_club_date (club_id, created_at)
) ENGINE=InnoDB;

-- Player/club history awards and records
CREATE TABLE IF NOT EXISTS player_awards (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    competition_id INT NOT NULL,
    award_type ENUM('PLAYER_OF_MATCH','PLAYER_OF_WEEK','TOP_SCORER','TOP_ASSIST','BEST_PLAYER','BEST_YOUNG_PLAYER') NOT NULL,
    player_id INT NOT NULL,
    club_id INT NOT NULL,
    match_id INT NULL,
    week_number INT NULL,
    score_value DECIMAL(10,2) DEFAULT 0,
    meta_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_award_scope (season_id, competition_id, award_type, match_id, week_number),
    INDEX idx_award_player (player_id, season_id),
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS club_honors (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    season_id INT NOT NULL,
    competition_id INT NOT NULL,
    honor_type ENUM('LEAGUE_TITLE','CUP_WIN','PROMOTION','RELEGATION','CHAMPIONS_QUALIFIED') NOT NULL,
    details VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_honor (club_id, season_id, competition_id, honor_type),
    INDEX idx_honor_club (club_id, created_at),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS club_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    record_key ENUM('TOP_SCORER','MOST_APPEARANCES','BEST_SEASON_SCORER') NOT NULL,
    player_id INT NOT NULL,
    record_value INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_record (club_id, record_key),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS club_legends (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    player_id INT NOT NULL,
    legend_score INT NOT NULL DEFAULT 0,
    status ENUM('ICON','LEGEND') DEFAULT 'LEGEND',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_legend_player (club_id, player_id),
    INDEX idx_legend_club_score (club_id, legend_score),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Administrative runtime operation logs
CREATE TABLE IF NOT EXISTS admin_operation_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT NOT NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_operation_date (admin_user_id, created_at),
    INDEX idx_admin_operation_entity (entity_type, entity_id),
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Daily cycle snapshots for scheduler observability/replay
CREATE TABLE IF NOT EXISTS daily_cycle_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cycle_date DATE NOT NULL,
    phase_key VARCHAR(50) NOT NULL,
    executed_at DATETIME NOT NULL,
    matches_simulated INT DEFAULT 0,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cycle_phase_execution (cycle_date, phase_key, executed_at)
) ENGINE=InnoDB;

-- Per-club daily cycle state (supports mixed one/two match clubs in same world day)
CREATE TABLE IF NOT EXISTS club_daily_cycle_states (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cycle_date DATE NOT NULL,
    club_id INT NOT NULL,
    matches_today TINYINT NOT NULL DEFAULT 1,
    profile_key ENUM('one_match','two_matches') NOT NULL,
    current_phase_key VARCHAR(50) NOT NULL,
    updated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cycle_club (cycle_date, club_id),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_cycle_phase (cycle_date, current_phase_key)
) ENGINE=InnoDB;

-- Runtime control/vacancy state for AI owner + caretaker continuity
CREATE TABLE IF NOT EXISTS club_control_runtime_states (
    club_id INT PRIMARY KEY,
    state_key VARCHAR(64) NOT NULL,
    ai_owner_active BOOLEAN DEFAULT 0,
    caretaker_active BOOLEAN DEFAULT 0,
    owner_vacant BOOLEAN DEFAULT 0,
    manager_vacant BOOLEAN DEFAULT 0,
    owner_vacancy_since DATE NULL,
    manager_vacancy_since DATE NULL,
    updated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_runtime_vacancy (owner_vacant, manager_vacant, caretaker_active)
) ENGINE=InnoDB;
