import csv
import os
import re

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

SCHEMA = """
DROP TABLE IF EXISTS movies;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS users;

CREATE TABLE movies (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    year INTEGER,
    genres TEXT
);

CREATE TABLE ratings (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    movie_id INTEGER NOT NULL,
    rating REAL NOT NULL,
    timestamp INTEGER NOT NULL
);

CREATE TABLE tags (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    movie_id INTEGER NOT NULL,
    tag TEXT NOT NULL,
    timestamp INTEGER NOT NULL
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT,
    gender TEXT,
    register_date TEXT,
    occupation TEXT
);
"""


def sql_text(value):
    return "'" + value.replace("'", "''") + "'"


def split_title(title):
    title = title.strip()
    match = re.match(r"^(.*)\s+\((\d{4})\)$", title)
    if match:
        return match.group(1).strip(), int(match.group(2))
    return title, None


def read_csv(name):
    with open(os.path.join(BASE_DIR, name), encoding="utf-8", newline="") as f:
        return list(csv.DictReader(f))


def write_movies(out):
    for row in read_csv("movies.csv"):
        title, year = split_title(row["title"])
        year_sql = "NULL" if year is None else str(year)
        out.write(
            "INSERT INTO movies (id, title, year, genres) VALUES "
            f"({int(row['movieId'])}, {sql_text(title)}, {year_sql}, {sql_text(row['genres'])});\n"
        )


def write_ratings(out):
    for i, row in enumerate(read_csv("ratings.csv"), start=1):
        out.write(
            "INSERT INTO ratings (id, user_id, movie_id, rating, timestamp) VALUES "
            f"({i}, {int(row['userId'])}, {int(row['movieId'])}, {float(row['rating'])}, {int(row['timestamp'])});\n"
        )


def write_tags(out):
    for i, row in enumerate(read_csv("tags.csv"), start=1):
        out.write(
            "INSERT INTO tags (id, user_id, movie_id, tag, timestamp) VALUES "
            f"({i}, {int(row['userId'])}, {int(row['movieId'])}, {sql_text(row['tag'])}, {int(row['timestamp'])});\n"
        )


def write_users(out):
    with open(os.path.join(BASE_DIR, "users.txt"), encoding="utf-8") as f:
        for line in f:
            line = line.rstrip("\r\n")
            if not line:
                continue
            user_id, name, email, gender, register_date, occupation = line.split("|")
            out.write(
                "INSERT INTO users (id, name, email, gender, register_date, occupation) VALUES "
                f"({int(user_id)}, {sql_text(name)}, {sql_text(email)}, {sql_text(gender)}, "
                f"{sql_text(register_date)}, {sql_text(occupation)});\n"
            )


def main():
    with open(os.path.join(BASE_DIR, "db_init.sql"), "w", encoding="utf-8", newline="\n") as out:
        out.write("BEGIN TRANSACTION;\n")
        out.write(SCHEMA)
        out.write("\n")
        write_movies(out)
        write_ratings(out)
        write_tags(out)
        write_users(out)
        out.write("\nCOMMIT;\n")


if __name__ == "__main__":
    main()
