-- Enable pgvector extension on default database
\c app
CREATE EXTENSION IF NOT EXISTS vector;

-- Create test database for integration tests
CREATE DATABASE app_test;
GRANT ALL PRIVILEGES ON DATABASE app_test TO app;

-- Connect to test database and grant schema privileges
\c app_test
GRANT ALL ON SCHEMA public TO app;
CREATE EXTENSION IF NOT EXISTS vector;
