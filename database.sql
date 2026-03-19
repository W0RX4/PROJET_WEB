CREATE TYPE user_type AS ENUM ('admin', 'etudiant', 'entreprise', 'tuteur', 'jury');

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type user_type NOT NULL,
    stage_id INTEGER
);

CREATE TABLE stages (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    student_id INTEGER,
    tutor_id INTEGER,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (tutor_id) REFERENCES users(id)
);

ALTER TABLE users
ADD CONSTRAINT fk_users_stage
FOREIGN KEY (stage_id) REFERENCES stages(id);