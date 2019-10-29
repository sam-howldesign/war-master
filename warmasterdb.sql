-- champs
-- id, lineid, name, stars, rank, level, sig
create table champs (
    id int(11) not null AUTO_INCREMENT,
    lineid varchar(50) not null,
    name varchar(50) not null,
    stars int(11) not null,
    rank int(11) not null,
    level int(11) not null,
    sig int(11) default null,
    primary key (id)
) ;

-- users
-- id, lineid, battlegroup, linename
create table users (
    id int(11) not null AUTO_INCREMENT,
    lineid varchar(50) not null,
    battlegroup varchar(20) not null,
    linename varchar(50) not null,
    primary key (id)
)