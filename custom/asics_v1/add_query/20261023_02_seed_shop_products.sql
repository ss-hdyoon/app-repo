-- Sample catalog. INSERT IGNORE keeps a re-run harmless (code is UNIQUE).
SET NAMES utf8mb4;

INSERT IGNORE INTO shop_product (code, name, category, price, sale_price, stock, color, description) VALUES
('RUN-KAY-31',  'GEL-KAYANO 31',       '러닝',     219000, NULL,   40, '#001e62', '안정성과 쿠셔닝을 함께 갖춘 장거리 러닝화. 발 아치를 받쳐 주는 구조로 오버프로네이션을 줄여 줍니다.'),
('RUN-NIM-27',  'GEL-NIMBUS 27',       '러닝',     229000, 199000, 35, '#4b6bfb', '부드러운 착지감을 원하는 러너를 위한 최상위 쿠셔닝 모델. 가벼운 폼으로 반발력을 더했습니다.'),
('RUN-NOV-5',   'NOVABLAST 5',         '러닝',     169000, NULL,   50, '#ff5a36', '통통 튀는 반발감의 데일리 트레이너. 템포 런과 조깅 모두에 잘 맞습니다.'),
('RUN-MSP-4',   'METASPEED SKY 4',     '레이싱',   329000, NULL,   12, '#00a6a6', '기록 단축을 위한 카본 플레이트 레이싱화. 보폭을 넓게 쓰는 러너에게 맞춘 설계입니다.'),
('TRL-TRB-13',  'GEL-TRABUCO 13',      '트레일',   189000, 159000, 20, '#3d7a3a', '거친 산길에서도 접지력이 좋은 트레일 러닝화. 바위 보호 플레이트를 넣었습니다.'),
('TEN-RES-10',  'GEL-RESOLUTION 10',   '테니스',   199000, NULL,   18, '#f2c230', '좌우 움직임이 많은 테니스 코트를 위한 안정형 테니스화.'),
('LIF-1130',    'GEL-1130',            '라이프스타일', 139000, 119000, 60, '#b7b7b7', '2000년대 러닝화 디자인을 되살린 데일리 스니커즈.'),
('LIF-KAH-14',  'GEL-KAHANA 14',       '라이프스타일', 159000, NULL,    0, '#6b4e3d', '아웃도어 무드의 스니커즈. 현재 품절 상태를 시험하기 위한 상품입니다.');
