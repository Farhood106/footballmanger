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
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Players
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
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

    wage INT DEFAULT 0,
    contract_end DATE,
    market_value BIGINT DEFAULT 0,

    is_injured BOOLEAN DEFAULT FALSE,
    injury_days INT DEFAULT 0,
    is_on_loan BOOLEAN DEFAULT FALSE,
    is_retired BOOLEAN DEFAULT FALSE,

    growth_rate DECIMAL(3,2) DEFAULT 1.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE SET NULL,
    INDEX idx_club (club_id),
    INDEX idx_position (position),
    INDEX idx_overall (overall)
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
    name VARCHAR(255) NOT NULL,
    type ENUM('LEAGUE','CUP','CHAMPIONS_LEAGUE','FRIENDLY') NOT NULL,
    country VARCHAR(100),
    level INT DEFAULT 1,
    teams_count INT DEFAULT 20
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

-- Club Seasons
CREATE TABLE club_seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    season_id INT NOT NULL,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_club_season (club_id, season_id)
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
    status ENUM('PENDING','COMPLETED','CANCELLED','REJECTED') DEFAULT 'PENDING',
    initiated_by INT NOT NULL,
    loan_end DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (from_club_id) REFERENCES clubs(id) ON DELETE SET NULL,
    FOREIGN KEY (to_club_id) REFERENCES clubs(id) ON DELETE SET NULL,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Player Season Stats
CREATE TABLE player_season_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    season_id INT NOT NULL,
    club_id INT NOT NULL,
    appearances INT DEFAULT 0,
    goals INT DEFAULT 0,
    assists INT DEFAULT 0,
    yellow_cards INT DEFAULT 0,
    red_cards INT DEFAULT 0,
    avg_rating DECIMAL(3,1) DEFAULT 0,
    minutes_played INT DEFAULT 0,
    clean_sheets INT DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_season_club (player_id, season_id, club_id)
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
