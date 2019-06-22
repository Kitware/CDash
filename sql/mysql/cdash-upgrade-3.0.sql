--
-- Laravel column needs for user
--
-- ALTER TABLE `user` ADD COLUMN `email_verified_at` timestamp NULL;
-- ALTER TABLE `user` ADD COLUMN `created_at` timestamp NULL;
-- ALTER TABLE `user` ADD COLUMN `created_at` timestamp NULL;
-- ALTER TABLE `user` ADD COLUMN `remember_token` varchar(100) NULL;

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

ALTER TABLE `user` ADD COLUMN `updated_at` TIMESTAMP NULL;
ALTER TABLE `user` ADD COLUMN `created_at` TIMESTAMP NULL;

ALTER TABLE `password` ADD COLUMN `updated_at` TIMESTAMP NULL;
ALTER TABLE `password` ADD COLUMN `created_at` TIMESTAMP NULL;
