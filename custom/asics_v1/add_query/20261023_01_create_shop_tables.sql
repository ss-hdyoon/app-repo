-- Mock store tables. Additive only, so the previous release keeps working (pre-deploy rule).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS shop_product (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(30)  NOT NULL UNIQUE,
  name        VARCHAR(100) NOT NULL,
  category    VARCHAR(30)  NOT NULL,
  price       INT          NOT NULL,
  sale_price  INT          NULL,
  stock       INT          NOT NULL DEFAULT 0,
  color       VARCHAR(7)   NOT NULL DEFAULT '#001e62',
  description TEXT         NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_order (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_no      VARCHAR(20)  NOT NULL UNIQUE,
  buyer_name    VARCHAR(50)  NOT NULL,
  buyer_phone   VARCHAR(20)  NOT NULL,
  buyer_email   VARCHAR(100) NOT NULL,
  zipcode       VARCHAR(10)  NOT NULL,
  address1      VARCHAR(200) NOT NULL,
  address2      VARCHAR(200) NOT NULL DEFAULT '',
  delivery_memo VARCHAR(200) NULL,
  pay_method    VARCHAR(20)  NOT NULL,
  items_amount  INT          NOT NULL,
  shipping_fee  INT          NOT NULL,
  total_amount  INT          NOT NULL,
  status        VARCHAR(20)  NOT NULL DEFAULT 'paid',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_order_item (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT          NOT NULL,
  product_id   INT          NOT NULL,
  product_name VARCHAR(100) NOT NULL,
  unit_price   INT          NOT NULL,
  qty          INT          NOT NULL,
  line_amount  INT          NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES shop_order (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PHP sessions live in the DB so the cart survives across the two web servers.
CREATE TABLE IF NOT EXISTS shop_session (
  id         VARCHAR(128) NOT NULL PRIMARY KEY,
  data       MEDIUMBLOB   NOT NULL,
  updated_at INT          NOT NULL,
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_admin (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
