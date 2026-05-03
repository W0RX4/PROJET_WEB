-- Schema synchronise avec les tables exposees par Supabase
-- Verification effectuee le 03/05/2026 via /rest/v1/ OpenAPI

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_type
        WHERE typname = 'user_type'
    ) THEN
        CREATE TYPE user_type AS ENUM ('admin', 'etudiant', 'entreprise', 'tuteur', 'jury');
    END IF;
END $$;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type user_type NOT NULL,
    stage_id INTEGER,
    status VARCHAR(50) DEFAULT 'active',
    admin_pending BOOLEAN DEFAULT FALSE
);

CREATE TABLE stages (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    filiere VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    student_id INTEGER,
    tutor_id INTEGER,
    competences TEXT,
    duration_weeks INTEGER,
    status VARCHAR(50) DEFAULT 'ouverte',
    company_id INTEGER,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (tutor_id) REFERENCES users(id),
    FOREIGN KEY (company_id) REFERENCES users(id)
);

CREATE TABLE candidatures (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    stage_id INTEGER NOT NULL,
    status VARCHAR(50) DEFAULT 'en attente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cv_url VARCHAR(500),
    cover_letter_url VARCHAR(500),
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (stage_id) REFERENCES stages(id)
);

CREATE TABLE conventions (
    id SERIAL PRIMARY KEY,
    stage_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    company_validated BOOLEAN DEFAULT FALSE,
    tutor_validated BOOLEAN DEFAULT FALSE,
    admin_validated BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stage_id) REFERENCES stages(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE formations (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE formation_requests (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL,
    formation_name VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'en attente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE cahier_stage (
    id SERIAL PRIMARY KEY,
    stage_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    entry_date DATE NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stage_id) REFERENCES stages(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE traces (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE two_factor_codes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE missions (
    id SERIAL PRIMARY KEY,
    stage_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stage_id) REFERENCES stages(id),
    FOREIGN KEY (company_id) REFERENCES users(id)
);

CREATE TABLE documents (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    stage_id INTEGER,
    type VARCHAR(100) NOT NULL,
    file_path TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (stage_id) REFERENCES stages(id)
);

CREATE TABLE remarques (
    id SERIAL PRIMARY KEY,
    stage_id INTEGER NOT NULL,
    author_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stage_id) REFERENCES stages(id),
    FOREIGN KEY (author_id) REFERENCES users(id)
);

ALTER TABLE users
ADD CONSTRAINT fk_users_stage
FOREIGN KEY (stage_id) REFERENCES stages(id);
