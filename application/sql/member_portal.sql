-- Run after auth_npk_group_pages.sql on existing installations. Safe to rerun.
DELETE page_access
FROM `group_pages` page_access
JOIN `groups` user_group ON user_group.id = page_access.group_id
WHERE user_group.name = 'members' AND page_access.page_key <> 'member_portal';

INSERT IGNORE INTO `group_pages` (`group_id`, `page_key`)
SELECT `id`, 'member_portal' FROM `groups` WHERE `name` = 'members';
