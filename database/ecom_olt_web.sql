-- ECOM OLT web/API monitoring support
-- Run once against the reseller database.
ALTER TABLE olts
  ADD COLUMN web_scheme VARCHAR(10) NOT NULL DEFAULT 'http' AFTER port,
  ADD COLUMN web_port SMALLINT UNSIGNED NOT NULL DEFAULT 9092 AFTER web_scheme,
  ADD COLUMN web_base_path VARCHAR(120) NOT NULL DEFAULT '/cgi-bin/h.cgi' AFTER web_port;

CREATE TABLE IF NOT EXISTS olt_web_modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  olt_id INT UNSIGNED NOT NULL,
  module_key VARCHAR(80) NOT NULL,
  module_name VARCHAR(150) NOT NULL,
  module_path VARCHAR(255) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_olt_module(olt_id,module_key),
  CONSTRAINT fk_olt_web_module FOREIGN KEY (olt_id) REFERENCES olts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Known ECOM modules from the supplied OLT web interface.
-- Add the optical-power module once its exact CGI module name is confirmed.
