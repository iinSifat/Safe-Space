<?php
// Updated by Shuvo - START
// Partial: renders blog post cards for blogs.php (also used for AJAX filter refresh)
?>
<?php if (count($posts) > 0): ?>
    <?php foreach ($posts as $post): ?>
        <?php
            $username = (string)($post['username'] ?? 'User');
            $avatarLetter = strtoupper(substr($username, 0, 1));
            $rawContent = (string)($post['content'] ?? '');
            $excerpt = function_exists('mb_substr') ? mb_substr($rawContent, 0, 180) : substr($rawContent, 0, 180);
            $excerpt = trim($excerpt);
            $hasMore = (function_exists('mb_strlen') ? mb_strlen($rawContent) : strlen($rawContent)) > (function_exists('mb_strlen') ? mb_strlen($excerpt) : strlen($excerpt));

            $myReaction = !empty($post['my_reaction']) ? (string)$post['my_reaction'] : null;
            $activeReaction = $myReaction && isset($reaction_assets[$myReaction]) ? $myReaction : 'like';
            $activeReactionLabel = $myReaction ? ucfirst($myReaction) : 'React';
            $reactionTotal = (int)($post['total_reactions'] ?? 0);
        ?>
        <div class="post-card" data-post-category="<?php echo htmlspecialchars((string)($post['category'] ?? '')); ?>" data-post-author-type="<?php echo htmlspecialchars((string)($post['user_type'] ?? '')); ?>">
            <a class="post-open" href="blog_view.php?blog_id=<?php echo (int)$post['blog_id']; ?>" aria-label="Open blog post">
                <div class="post-header-row">
                    <div class="post-avatar" aria-hidden="true"><?php echo htmlspecialchars($avatarLetter); ?></div>
                    <div class="post-head-text">
                        <div class="post-name-row">
                            <span class="post-name"><?php echo htmlspecialchars($username); ?></span>
                            <?php if (($post['user_type'] ?? '') === 'professional'): ?>
                                <?php
                                    $authority = professional_expert_content_label(($post['professional_specialization'] ?? ''), ($post['professional_verification_status'] ?? ''));
                                ?>
                                <span class="pro-badge"><?php echo htmlspecialchars($authority); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="post-sub">
                            <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                            <span aria-hidden="true">·</span>
                            <span class="category-chip"><?php echo htmlspecialchars($post['category']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                <?php if ($excerpt !== ''): ?>
                    <p class="post-excerpt">
                        <?php echo htmlspecialchars($excerpt); ?><?php echo $hasMore ? '…' : ''; ?>
                    </p>
                <?php endif; ?>
            </a>

            <div class="post-stats">
                <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <?php echo (int)$post['view_count']; ?> views</span>
                <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg> <strong data-reaction-total><?php echo $reactionTotal; ?></strong> reactions</span>
                <span><svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> <strong data-comment-count><?php echo (int)$post['comment_count']; ?></strong> comments</span>
            </div>

            <div class="post-actions" aria-label="Post actions">
                <div class="feed-reaction" data-blog-id="<?php echo (int)$post['blog_id']; ?>">
                    <button type="button" class="reaction-trigger" aria-haspopup="true" aria-expanded="false">
                        <img class="active-reaction-icon" src="<?php echo htmlspecialchars($reaction_assets[$activeReaction]); ?>" alt="Reaction">
                        <span class="active-reaction-label"><?php echo htmlspecialchars($activeReactionLabel); ?></span>
                    </button>
                    <div class="reaction-popup" role="menu" aria-label="Choose a reaction">
                        <?php foreach ($reaction_types as $reaction_type): ?>
                            <button class="reaction-option" type="button" data-reaction="<?php echo $reaction_type; ?>" aria-label="React with <?php echo ucfirst($reaction_type); ?>">
                                <img src="<?php echo htmlspecialchars($reaction_assets[$reaction_type]); ?>" alt="<?php echo ucfirst($reaction_type); ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" class="post-action post-action-comment">
                    <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    Comment
                </button>
                <button type="button" class="post-action post-action-share" data-blog-id="<?php echo (int)$post['blog_id']; ?>">↗ Share</button>
            </div>

            <div class="feed-comment" hidden>
                <div class="feed-comment-inner">
                    <textarea class="feed-comment-text" placeholder="Write a comment..."></textarea>
                    <div class="feed-comment-actions">
                        <button type="button" class="btn-cancel-mini">Cancel</button>
                        <button type="button" class="btn-primary-mini">Post</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
        <?php // Updated by Shuvo - START ?>
        <?php if (!empty($search_query)): ?>
            <p style="margin: 0;">No results found.</p>
        <?php else: ?>
            <p style="margin: 0;">No blog posts yet in this category.</p>
        <?php endif; ?>
        <?php // Updated by Shuvo - END ?>
    </div>
<?php endif; ?>
<?php
// Updated by Shuvo - END
?>
