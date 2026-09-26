-- Schema TRAXTER RH - Recrutamento e Seleção
-- Estrutura completa (sem dados). Compatível com MySQL 8+ / MariaDB 10.4+.
-- Instalação local: php scripts/setup_full.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/*M!999999\- enable the sandbox mode */ 
CREATE TABLE IF NOT EXISTS `auditoria_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_usuario_id` int(11) DEFAULT NULL,
  `target_usuario_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_auditoria_actor` (`actor_usuario_id`),
  KEY `fk_auditoria_target` (`target_usuario_id`),
  CONSTRAINT `fk_auditoria_actor` FOREIGN KEY (`actor_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_auditoria_target` FOREIGN KEY (`target_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `beneficios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `parceiro` varchar(120) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `candidatura_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidatura_id` int(11) NOT NULL,
  `status_anterior` varchar(30) DEFAULT NULL,
  `status_novo` varchar(30) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_hist_candidatura` (`candidatura_id`),
  KEY `fk_hist_usuario` (`usuario_id`),
  CONSTRAINT `fk_hist_candidatura` FOREIGN KEY (`candidatura_id`) REFERENCES `candidaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `candidaturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vaga_id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `telefone` varchar(40) NOT NULL,
  `cpf` varchar(11) NOT NULL,
  `cargo_pretendido` varchar(120) NOT NULL,
  `experiencia` text NOT NULL,
  `pdf_path` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'novo',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `stage_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `indicacao_colaborador` tinyint(1) NOT NULL DEFAULT 0,
  `indicacao_colaborador_nome` varchar(120) DEFAULT NULL,
  `indicacao_data_contratacao` datetime DEFAULT NULL,
  `indicacao_data_fim_experiencia` date DEFAULT NULL,
  `indicacao_pagamento_realizado` tinyint(1) NOT NULL DEFAULT 0,
  `indicacao_data_pagamento` date DEFAULT NULL,
  `indicacao_pagamento_registrado_em` datetime DEFAULT NULL,
  `indicacao_valor_comissao` decimal(10,2) DEFAULT NULL,
  `indicacao_metodo_pagamento` varchar(50) DEFAULT NULL,
  `indicacao_pagamento_status` varchar(20) NOT NULL DEFAULT 'pendente',
  `anonimizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cand_cpf_vaga` (`cpf`,`vaga_id`),
  KEY `fk_cand_vaga` (`vaga_id`),
  KEY `idx_candidaturas_stage_id` (`stage_id`),
  CONSTRAINT `fk_cand_stage` FOREIGN KEY (`stage_id`) REFERENCES `pipeline_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cand_vaga` FOREIGN KEY (`vaga_id`) REFERENCES `vagas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `indicacao_pagamento_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidatura_id` int(11) NOT NULL,
  `data_anterior` date DEFAULT NULL,
  `data_nova` date NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ind_pag_cand` (`candidatura_id`),
  KEY `fk_ind_pag_user` (`usuario_id`),
  CONSTRAINT `fk_ind_pag_cand` FOREIGN KEY (`candidatura_id`) REFERENCES `candidaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ind_pag_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `notas_recrutador` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidatura_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nota` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_nota_cand` (`candidatura_id`),
  KEY `fk_nota_user` (`usuario_id`),
  CONSTRAINT `fk_nota_cand` FOREIGN KEY (`candidatura_id`) REFERENCES `candidaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nota_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_password_reset_usuario` (`usuario_id`),
  CONSTRAINT `fk_password_reset_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `pipeline_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidatura_id` int(11) NOT NULL,
  `stage_anterior_id` int(11) DEFAULT NULL,
  `stage_novo_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_mov_cand` (`candidatura_id`),
  KEY `fk_mov_stage_ant` (`stage_anterior_id`),
  KEY `fk_mov_stage_new` (`stage_novo_id`),
  KEY `fk_mov_user` (`usuario_id`),
  CONSTRAINT `fk_mov_cand` FOREIGN KEY (`candidatura_id`) REFERENCES `candidaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mov_stage_ant` FOREIGN KEY (`stage_anterior_id`) REFERENCES `pipeline_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_stage_new` FOREIGN KEY (`stage_novo_id`) REFERENCES `pipeline_stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mov_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `pipeline_stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `cor` varchar(7) DEFAULT '#cccccc',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `requisitos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vaga_id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `obrigatorio` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_req_vaga` (`vaga_id`),
  CONSTRAINT `fk_req_vaga` FOREIGN KEY (`vaga_id`) REFERENCES `vagas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `role` enum('admin','rh','viewer') NOT NULL DEFAULT 'viewer',
  `is_supervisor` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `last_password_reset_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `vagas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `descricao` text NOT NULL,
  `requisitos` text NOT NULL,
  `area` varchar(100) DEFAULT NULL,
  `local` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Etapas padrão do funil de seleção
INSERT IGNORE INTO pipeline_stages (id, nome, ordem, cor) VALUES
  (1, 'Novo', 1, '#3b82f6'),
  (2, 'Triagem', 2, '#f59e0b'),
  (3, 'Entrevista', 3, '#8b5cf6'),
  (4, 'Proposta', 4, '#1d2d44'),
  (5, 'Contratado', 5, '#1d2d44'),
  (6, 'Rejeitado', 6, '#ef4444');

-- Registro de consentimento LGPD dos candidatos
CREATE TABLE IF NOT EXISTS `lgpd_consentimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidatura_id` int(11) NOT NULL,
  `versao_politica` varchar(20) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `aceito_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consent_candidatura` (`candidatura_id`),
  CONSTRAINT `fk_consent_candidatura` FOREIGN KEY (`candidatura_id`) REFERENCES `candidaturas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
