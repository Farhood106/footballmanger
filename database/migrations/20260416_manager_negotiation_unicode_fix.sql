-- Ensure manager contract negotiation table is utf8mb4 for Persian/Unicode text safety.

ALTER TABLE manager_contract_negotiations
    CONVERT TO CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

ALTER TABLE manager_contract_negotiations
    MODIFY COLUMN club_objective VARCHAR(255)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci
    NULL;
