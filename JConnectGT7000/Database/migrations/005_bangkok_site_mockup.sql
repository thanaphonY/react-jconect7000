-- Bangkok district mock sites under two regions (additive seed for map/UI tests).

INSERT INTO site_nodes (parent_id, name, node_type, sort_order)
SELECT m.id, 'ฝั่งพระนคร', 'Region', 10
FROM site_nodes m
WHERE m.node_type = 'Main'
  AND NOT EXISTS (
    SELECT 1 FROM site_nodes r
    WHERE r.parent_id = m.id AND r.node_type = 'Region' AND r.name = 'ฝั่งพระนคร'
  )
LIMIT 1;

INSERT INTO site_nodes (parent_id, name, node_type, sort_order)
SELECT m.id, 'ฝั่งธนบุรี', 'Region', 20
FROM site_nodes m
WHERE m.node_type = 'Main'
  AND NOT EXISTS (
    SELECT 1 FROM site_nodes r
    WHERE r.parent_id = m.id AND r.node_type = 'Region' AND r.name = 'ฝั่งธนบุรี'
  )
LIMIT 1;

-- parent, name, sort_order, latitude, longitude (approx district centers)
INSERT INTO site_nodes (parent_id, name, node_type, sort_order, latitude, longitude)
SELECT r.id, v.name, 'Site', v.sort_order, v.lat, v.lng
FROM site_nodes r
CROSS JOIN (VALUES
  ('พระนคร', 1, 13.756300, 100.501800),
  ('ดุสิต', 2, 13.778300, 100.516700),
  ('ป้อมปราบศัตรูพ่าย', 3, 13.758000, 100.513000),
  ('สัมพันธวงศ์', 4, 13.737000, 100.514000),
  ('ปทุมวัน', 5, 13.746000, 100.534000),
  ('บางรัก', 6, 13.723000, 100.521000),
  ('สาทร', 7, 13.708000, 100.526000),
  ('บางคอแหลม', 8, 13.694000, 100.501000),
  ('ยานนาวา', 9, 13.696000, 100.543000),
  ('คลองเตย', 10, 13.708000, 100.583000),
  ('วัฒนา', 11, 13.730000, 100.561000),
  ('พระโขนง', 12, 13.703000, 100.601000),
  ('สวนหลวง', 13, 13.735000, 100.632000),
  ('บางนา', 14, 13.665000, 100.606000),
  ('ดินแดง', 15, 13.769000, 100.552000),
  ('ห้วยขวาง', 16, 13.776000, 100.579000),
  ('ราชเทวี', 17, 13.758000, 100.537000),
  ('พญาไท', 18, 13.780000, 100.544000),
  ('วังทองหลาง', 19, 13.778000, 100.622000),
  ('บางกะปิ', 20, 13.796000, 100.638000),
  ('บึงกุ่ม', 21, 13.793000, 100.648000),
  ('ลาดพร้าว', 22, 13.812000, 100.608000),
  ('คันนายาว', 23, 13.826000, 100.653000),
  ('สะพานสูง', 24, 13.769000, 100.685000),
  ('บางเขน', 25, 13.872000, 100.596000),
  ('สายไหม', 26, 13.899000, 100.652000),
  ('จตุจักร', 27, 13.799000, 100.549000),
  ('หลักสี่', 28, 13.882000, 100.576000),
  ('ดอนเมือง', 29, 13.913000, 100.601000),
  ('มีนบุรี', 30, 13.813000, 100.732000),
  ('ลาดกระบัง', 31, 13.723000, 100.783000),
  ('หนองจอก', 32, 13.855000, 100.792000),
  ('คลองสามวา', 33, 13.863000, 100.704000),
  ('ประเวศ', 34, 13.707000, 100.683000)
) AS v(name, sort_order, lat, lng)
WHERE r.node_type = 'Region' AND r.name = 'ฝั่งพระนคร'
  AND NOT EXISTS (
    SELECT 1 FROM site_nodes s
    WHERE s.parent_id = r.id AND s.node_type = 'Site' AND s.name = v.name
  );

INSERT INTO site_nodes (parent_id, name, node_type, sort_order, latitude, longitude)
SELECT r.id, v.name, 'Site', v.sort_order, v.lat, v.lng
FROM site_nodes r
CROSS JOIN (VALUES
  ('ธนบุรี', 1, 13.724000, 100.485000),
  ('คลองสาน', 2, 13.732000, 100.508000),
  ('บางกอกใหญ่', 3, 13.715000, 100.476000),
  ('บางกอกน้อย', 4, 13.762000, 100.495000),
  ('บางพลัด', 5, 13.794000, 100.493000),
  ('ตลิ่งชัน', 6, 13.776000, 100.456000),
  ('ทวีวัฒนา', 7, 13.774000, 100.362000),
  ('ภาษีเจริญ', 8, 13.723000, 100.454000),
  ('บางแค', 9, 13.695000, 100.435000),
  ('หนองแขม', 10, 13.704000, 100.352000),
  ('จอมทอง', 11, 13.679000, 100.485000),
  ('บางขุนเทียน', 12, 13.621000, 100.436000),
  ('บางบอน', 13, 13.660000, 100.403000),
  ('ราษฎร์บูรณะ', 14, 13.682000, 100.508000),
  ('ทุ่งครุ', 15, 13.635000, 100.508000)
) AS v(name, sort_order, lat, lng)
WHERE r.node_type = 'Region' AND r.name = 'ฝั่งธนบุรี'
  AND NOT EXISTS (
    SELECT 1 FROM site_nodes s
    WHERE s.parent_id = r.id AND s.node_type = 'Site' AND s.name = v.name
  );

INSERT INTO schema_migrations (filename)
VALUES ('005_bangkok_site_mockup.sql')
ON CONFLICT (filename) DO NOTHING;
