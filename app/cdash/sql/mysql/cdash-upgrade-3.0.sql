--
-- Table structure for table `migrations`
--
CREATE TABLE IF NOT EXISTS migrations
(
	id int unsigned auto_increment
		primary key,
	migration varchar(255) not null,
	batch int not null
) collate=utf8mb4_unicode_ci;
