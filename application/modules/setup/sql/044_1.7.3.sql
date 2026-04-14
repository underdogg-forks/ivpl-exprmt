CREATE TABLE `ip_integrations` (
    `integration_id` int(11) NOT NULL AUTO_INCREMENT,
    `integration_name` varchar(255) NOT NULL,
    `integration_provider` varchar(255) NOT NULL,
    `integration_status` tinyint(1) NOT NULL DEFAULT 1,
    `integration_created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `integration_updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`integration_id`),
    UNIQUE KEY `integration_provider_unique` (`integration_provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `ip_integration_settings` (
    `integration_setting_id` int(11) NOT NULL AUTO_INCREMENT,
    `integration_id` int(11) NOT NULL,
    `setting_key` varchar(190) NOT NULL,
    `setting_value` text NOT NULL,
    `is_encrypted` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`integration_setting_id`),
    UNIQUE KEY `integration_setting_unique` (`integration_id`, `setting_key`),
    CONSTRAINT `fk_integration_settings_integration`
        FOREIGN KEY (`integration_id`) REFERENCES `ip_integrations` (`integration_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `ip_integration_tokens` (
    `integration_token_id` int(11) NOT NULL AUTO_INCREMENT,
    `integration_id` int(11) NOT NULL,
    `token_type` varchar(50) NOT NULL,
    `token_value` text NOT NULL,
    `expires_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`integration_token_id`),
    KEY `integration_tokens_integration_id` (`integration_id`),
    KEY `integration_tokens_expires_at` (`expires_at`),
    CONSTRAINT `fk_integration_tokens_integration`
        FOREIGN KEY (`integration_id`) REFERENCES `ip_integrations` (`integration_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `ip_integration_logs` (
    `integration_log_id` int(11) NOT NULL AUTO_INCREMENT,
    `integration_id` int(11) NOT NULL,
    `log_event` varchar(190) NOT NULL,
    `log_status` varchar(50) NOT NULL,
    `log_context` longtext DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`integration_log_id`),
    KEY `integration_logs_integration_id` (`integration_id`),
    KEY `integration_logs_created_at` (`created_at`),
    CONSTRAINT `fk_integration_logs_integration`
        FOREIGN KEY (`integration_id`) REFERENCES `ip_integrations` (`integration_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `ip_clients`
    ADD COLUMN `client_peppol_id` varchar(255) NULL AFTER `client_tax_code`;
