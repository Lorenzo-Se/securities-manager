CREATE USER laravel WITH PASSWORD 'laravel';
CREATE DATABASE securities_manager OWNER laravel;
GRANT ALL PRIVILEGES ON DATABASE securities_manager TO laravel;
