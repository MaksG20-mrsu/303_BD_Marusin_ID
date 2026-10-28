PRAGMA foreign_keys = ON;

BEGIN TRANSACTION;

INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Илья', 'Марусин', 'ilya.marusin@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));

INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Марина', 'Лепкина', 'marina.lepkina@example.com', 'female', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));

INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Богдан', 'Малухин', 'bogdan.malukhin@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));

INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Даниил', 'Меньшаков', 'daniil.menshakov@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));

INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Михаил', 'Мехонцев', 'mikhail.mekhontsev@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));

INSERT INTO movies (title, year) VALUES ('The Batman', 2022);
INSERT INTO movies (title, year) VALUES ('Encanto', 2021);
INSERT INTO movies (title, year) VALUES ('A Quiet Place Part II', 2020);

INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.id, g.id FROM movies m JOIN genres g ON g.name IN ('Action', 'Crime', 'Drama')
WHERE m.title = 'The Batman' AND m.year = 2022;

INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.id, g.id FROM movies m JOIN genres g ON g.name IN ('Animation', 'Children', 'Comedy', 'Fantasy', 'Musical')
WHERE m.title = 'Encanto' AND m.year = 2021;

INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.id, g.id FROM movies m JOIN genres g ON g.name IN ('Horror', 'Sci-Fi', 'Thriller')
WHERE m.title = 'A Quiet Place Part II' AND m.year = 2020;

INSERT INTO ratings (user_id, movie_id, rating)
VALUES ((SELECT id FROM users WHERE email = 'ilya.marusin@example.com'),
        (SELECT id FROM movies WHERE title = 'The Batman' AND year = 2022), 4.5);

INSERT INTO ratings (user_id, movie_id, rating)
VALUES ((SELECT id FROM users WHERE email = 'ilya.marusin@example.com'),
        (SELECT id FROM movies WHERE title = 'Encanto' AND year = 2021), 4.0);

INSERT INTO ratings (user_id, movie_id, rating)
VALUES ((SELECT id FROM users WHERE email = 'ilya.marusin@example.com'),
        (SELECT id FROM movies WHERE title = 'A Quiet Place Part II' AND year = 2020), 3.5);

COMMIT;
