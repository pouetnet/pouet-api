-- Minimal seed so foreign keys (addedUser etc.) resolve even before/without import
INSERT INTO `users` (`id`,`nickname`,`level`,`avatar`,`registerDate`,`glops`,`thumbups`,`thumbdowns`,`permissionSubmitItems`,`permissionOpenBBS`,`permissionPostBBS`,`permissionPostOneliner`) VALUES
(1,'analogue','administrator','bobble.gif','2000-06-25 17:59:58',845,211,39,1,1,1,1);
INSERT INTO `usersettings` (`id`) VALUES (1);

-- Fake oneliners so the oneliner API endpoint has something to return before/without a data import
INSERT INTO `oneliner` (`message`,`who`,`addedDate`) VALUES
('welcome to the all new pouet api',1,'2026-10-01 12:00:00'),
('testing oneliner endpoint with some fake data',1,'2026-10-02 09:30:00'),
('this one has a link: https://www.pouet.net/',1,'2026-10-03 18:45:00'),
('lorem ipsum dolor sit amet, consectetur adipiscing elit',1,'2026-10-04 21:15:00'),
('scene is not dead, it just smells funny',1,'2026-10-05 07:05:00');
