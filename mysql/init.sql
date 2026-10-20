SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS properties (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  property_type VARCHAR(40) NOT NULL,
  price BIGINT UNSIGNED NOT NULL,
  area DECIMAL(8,2) NOT NULL,
  address VARCHAR(240) NOT NULL,
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_properties_search (property_type, price, area)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id BIGINT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  message VARCHAR(2000) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_contacts_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO properties (title, description, property_type, price, area, address, image_url) VALUES
('Nhà phố hiện đại gần trung tâm', 'Nhà 3 tầng thoáng sáng, đường ô tô, sổ hồng riêng. Phù hợp gia đình trẻ.', 'Nhà bán', 4850000000, 86.50, 'Quận Bình Thạnh, TP. Hồ Chí Minh', 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80'),
('Căn hộ hai phòng ngủ view công viên', 'Căn hộ nội thất cơ bản, ban công rộng, tiện ích đầy đủ và bảo vệ 24/7.', 'Căn hộ', 2850000000, 72.00, 'Thành phố Thủ Đức, TP. Hồ Chí Minh', 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80'),
('Đất thổ cư khu dân cư yên tĩnh', 'Lô đất vuông vức, hạ tầng hoàn chỉnh, phù hợp xây nhà hoặc đầu tư dài hạn.', 'Đất nền', 1950000000, 110.00, 'Dĩ An, Bình Dương', 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1200&q=80');
