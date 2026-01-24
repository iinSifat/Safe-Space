<?php
/**
 * Expects these variables in scope:
 * $community_id (int), $community (array), $member_count (int), $post_count (int)
 * $my_role (?string), $is_member (bool), $can_manage (bool), $activeTab (string)
 */

$communityName = (string)($community['name'] ?? 'Community');
$avatar = mb_strtoupper(mb_substr($communityName, 0, 2));
$focus = ucfirst((string)($community['focus_tag'] ?? 'community'));
$sensitivity = ucfirst((string)($community['sensitivity_level'] ?? ''));
$joinHref = 'community_about.php?community_id=' . (int)$community_id . '#membership';
?>

<div class="group-hero">
    <div class="group-cover">
        <div class="group-inner">
            <div class="group-title-row">
                <div class="group-avatar"><?php echo htmlspecialchars($avatar); ?></div>
                <div>
                    <h1 class="group-title"><?php echo htmlspecialchars($communityName); ?></h1>
                    <div class="group-meta">
                        <?php echo htmlspecialchars($focus); ?>
                        &middot; <?php echo (int)$member_count; ?> members
                        &middot; <?php echo (int)$post_count; ?> posts
                        <?php if ($sensitivity !== ''): ?>
                            &middot; Sensitivity: <?php echo htmlspecialchars($sensitivity); ?>
                        <?php endif; ?>
                    </div>
                    <div class="hero-badges">
                        <?php if ((int)($community['allow_anonymous_posts'] ?? 0) === 1): ?>
                            <span class="hero-badge">Anonymous posting allowed</span>
                        <?php endif; ?>
                        <?php if (!empty($my_role)): ?>
                            <span class="hero-badge">Role: <?php echo htmlspecialchars(ucfirst((string)$my_role)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_member): ?>
                    <div class="group-actions">
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars($joinHref); ?>">Join / Request</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="community-nav">
    <div class="category-filters">
        <a class="category-btn <?php echo ($activeTab === 'about') ? 'active' : ''; ?>" href="community_about.php?community_id=<?php echo (int)$community_id; ?>">About</a>
        <a class="category-btn <?php echo ($activeTab === 'discussion') ? 'active' : ''; ?>" href="community_view.php?community_id=<?php echo (int)$community_id; ?>">Discussion</a>
        <?php if ($can_manage): ?>
            <a class="category-btn <?php echo ($activeTab === 'manage') ? 'active' : ''; ?>" href="community_creator_dashboard.php?community_id=<?php echo (int)$community_id; ?>">Manage Community</a>
        <?php endif; ?>
    </div>
</div>
