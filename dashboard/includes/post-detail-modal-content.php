<?php
// This file contains the post detail content for display in modal
// Called from forum_view.php with variables already set
?>

<style>
    .modal-post-header {
        background: white;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-radius: var(--radius-sm);
    }

    .modal-post-header-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
    }

    .modal-post-content {
        background: white;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-radius: var(--radius-sm);
        line-height: 1.8;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .modal-post-title {
        font-size: 1.5rem;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
        font-weight: 700;
    }

    .modal-post-category {
        display: inline-block;
        background: rgba(107, 155, 209, 0.15);
        color: var(--primary-color);
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
    }

    /* LinkedIn-style reaction bar */
    .post-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: white;
        border: 1px solid var(--light-gray);
        border-radius: var(--radius-md);
        padding: 0.75rem 1rem;
        box-shadow: var(--shadow-sm);
        margin-bottom: 1.5rem;
    }

    .post-actions-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .post-actions-meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .action-chip {
        border: 1px solid var(--light-gray);
        background: var(--light-bg);
        color: var(--text-primary);
        border-radius: 999px;
        padding: 0.4rem 0.9rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: all var(--transition-fast);
    }

    .action-chip:hover {
        background: white;
        border-color: var(--primary-color);
        box-shadow: var(--shadow-sm);
        color: var(--primary-dark);
    }

    .reaction-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .reaction-trigger {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: white;
        border: 1px solid var(--light-gray);
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        cursor: pointer;
        box-shadow: var(--shadow-sm);
        transition: transform var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast);
    }

    .reaction-trigger img {
        width: 22px;
        height: 22px;
        object-fit: contain;
    }

    .reaction-trigger:hover {
        border-color: var(--primary-color);
        box-shadow: var(--shadow-md);
        transform: translateY(-1px);
    }

    .reaction-popup {
        position: absolute;
        bottom: 100%;
        left: 0;
        transform: translateY(-2px) scale(0.98);
        display: flex;
        gap: 0.25rem;
        padding: 0.5rem 0.6rem;
        background: white;
        border-radius: 999px;
        box-shadow: 0 12px 30px rgba(12, 27, 51, 0.15);
        border: 1px solid var(--light-gray);
        opacity: 0;
        pointer-events: none;
        transition: opacity var(--transition-fast), transform var(--transition-fast);
        z-index: 20;
    }

    .reaction-wrapper:hover .reaction-popup,
    .reaction-wrapper.show-popup .reaction-popup {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(-2px) scale(1);
    }

    .reaction-option {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: none;
        background: transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }

    .reaction-option:hover {
        transform: translateY(-2px) scale(1.05);
        box-shadow: var(--shadow-sm);
    }

    .reaction-option img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .reaction-count-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: var(--light-bg);
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        border: 1px solid var(--light-gray);
    }

    .reaction-count-chip img {
        width: 18px;
        height: 18px;
    }

    .post-date-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: var(--text-secondary);
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .post-actions {
            flex-direction: column;
            align-items: flex-start;
        }

        .post-actions-left {
            width: 100%;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
    }
</style>

<!-- Post Header -->
<div class="modal-post-header">
    <div class="modal-post-category"><?php echo htmlspecialchars($post['category']); ?></div>
    <h2 class="modal-post-title"><?php echo htmlspecialchars($post['title']); ?></h2>
    <div class="modal-post-header-meta">
        <span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg><?php echo htmlspecialchars($post['username']); ?></span>
        <span>📅 <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
        <span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><?php echo $post['view_count']; ?> views</span>
    </div>
</div>

<!-- Post Content -->
<div class="modal-post-content">
    <?php echo htmlspecialchars($post['content']); ?>
</div>

<!-- Reactions & Actions -->
<div class="post-actions" aria-label="Post actions">
    <div class="post-actions-left">
        <div class="reaction-wrapper" id="reactionWrapper" data-post-id="<?php echo $post_id; ?>">
            <button type="button" class="reaction-trigger" id="reactionTrigger" aria-haspopup="true" aria-expanded="false">
                <img id="activeReactionIcon" src="<?php echo htmlspecialchars($reaction_assets[$user_reaction ?? 'like']); ?>" alt="Current reaction">
                <span id="activeReactionLabel" style="font-weight: 700; color: var(--text-primary);">
                    <?php echo $user_reaction ? ucfirst($user_reaction) : 'Like'; ?>
                </span>
            </button>
            <div class="reaction-popup" id="reactionPopup" role="menu" aria-label="Choose a reaction">
                <?php foreach ($reaction_types as $reaction_type): ?>
                    <button class="reaction-option" data-reaction="<?php echo $reaction_type; ?>" aria-label="React with <?php echo ucfirst($reaction_type); ?>">
                        <img src="<?php echo htmlspecialchars($reaction_assets[$reaction_type]); ?>" alt="<?php echo ucfirst($reaction_type); ?> icon">
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="action-chip" type="button" data-scroll-target="#modalReplySection">💬 Comment</button>
    </div>
    <div class="post-actions-meta">
        <span class="reaction-count-chip" id="reactionCountChip">
            <img src="<?php echo htmlspecialchars($reaction_assets['like']); ?>" alt="Reactions">
            <span><strong id="reactionTotal"><?php echo $total_reactions; ?></strong> reactions</span>
        </span>
    </div>
</div>

<!-- Replies Section -->
<div class="modal-replies-section" style="background: white; padding: 1.5rem; margin-bottom: 1rem; border-radius: var(--radius-sm);">
    <h3 class="modal-replies-title" style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem;">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Replies (<?php echo count($replies); ?>)
    </h3>

    <?php if (count($replies) > 0): ?>
        <?php foreach ($replies as $reply): ?>
            <div class="reply-item" style="padding: 1rem 0; border-bottom: 1px solid var(--light-gray);">
                <div class="reply-meta" style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem;">
                    <span class="reply-author" style="font-weight: 600; color: var(--text-primary);"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 4px; vertical-align: middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z"/></svg><?php echo htmlspecialchars($reply['username']); ?></span>
                    <span class="reply-date" style="color: var(--text-secondary);"><?php echo date('M j, Y', strtotime($reply['created_at'])); ?></span>
                </div>
                <div class="reply-content" style="color: var(--text-primary); line-height: 1.6; white-space: pre-wrap; word-wrap: break-word;">
                    <?php echo htmlspecialchars($reply['content']); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color: var(--text-secondary); text-align: center; padding: 1rem; font-size: 0.9rem;">
            No replies yet. Be the first to respond!
        </p>
    <?php endif; ?>
</div>

<!-- New Reply Section -->
<div class="modal-new-reply-section" id="modalReplySection" style="background: linear-gradient(135deg, rgba(107, 155, 209, 0.05), rgba(184, 166, 217, 0.05)); padding: 1.5rem; border-radius: var(--radius-sm);">
    <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem;">
        Add Your Support
    </h3>
    <form method="POST" action="">
        <div style="margin-bottom: 1rem;">
            <textarea id="replyForm" name="reply_content" placeholder="Share your thoughts, experiences, or advice..." required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-family: inherit; font-size: 0.95rem; resize: vertical; min-height: 100px;"></textarea>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button type="submit" name="add_reply" class="btn btn-primary" style="flex: 1; padding: 0.75rem;">Post Reply (+10 pts)</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const reactionWrapper = document.getElementById('reactionWrapper');
        const reactionPopup = document.getElementById('reactionPopup');
        const reactionTrigger = document.getElementById('reactionTrigger');
        const activeReactionIcon = document.getElementById('activeReactionIcon');
        const activeReactionLabel = document.getElementById('activeReactionLabel');
        const reactionTotalEl = document.getElementById('reactionTotal');
        const scrollButtons = document.querySelectorAll('[data-scroll-target]');

        const reactionAssets = <?php echo json_encode($reaction_assets); ?>;
        const reactionLabels = <?php echo json_encode(array_combine($reaction_types, array_map('ucfirst', $reaction_types))); ?>;
        let reactionCounts = <?php echo json_encode($reaction_counts); ?>;
        let userReaction = <?php echo $user_reaction ? json_encode($user_reaction) : 'null'; ?>;
        const postId = <?php echo $post_id; ?>;

        const getTotalReactions = () => Object.values(reactionCounts).reduce((total, value) => total + parseInt(value || 0, 10), 0);

        const setActiveReaction = (type) => {
            const fallback = 'like';
            const safeType = reactionAssets[type] ? type : fallback;
            activeReactionIcon.src = reactionAssets[safeType];
            activeReactionLabel.textContent = reactionLabels[safeType] || 'Like';
            reactionTotalEl.textContent = getTotalReactions();
        };

        const showPopup = () => {
            reactionWrapper.classList.add('show-popup');
            reactionTrigger.setAttribute('aria-expanded', 'true');
        };

        const hidePopup = () => {
            reactionWrapper.classList.remove('show-popup');
            reactionTrigger.setAttribute('aria-expanded', 'false');
        };

        // Desktop hover behavior
        reactionTrigger.addEventListener('mouseenter', showPopup);
        reactionWrapper.addEventListener('mouseleave', hidePopup);
        reactionTrigger.addEventListener('focus', showPopup);
        reactionTrigger.addEventListener('blur', hidePopup);

        // Mobile long-press behavior
        let pressTimer;
        reactionTrigger.addEventListener('touchstart', () => {
            pressTimer = setTimeout(showPopup, 260);
        });
        reactionTrigger.addEventListener('touchend', () => {
            clearTimeout(pressTimer);
            setTimeout(hidePopup, 240);
        });

        // Quick tap defaults to Like
        reactionTrigger.addEventListener('click', () => {
            if (!reactionWrapper.classList.contains('show-popup')) {
                sendReaction(userReaction || 'like');
            }
        });

        // Reaction selection
        reactionPopup.querySelectorAll('.reaction-option').forEach((btn) => {
            btn.addEventListener('click', () => {
                const selected = btn.getAttribute('data-reaction');
                sendReaction(selected);
                hidePopup();
            });
        });

        const sendReaction = async (reactionType) => {
            try {
                const response = await fetch('forum_reaction_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        reaction_type: reactionType,
                    }),
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'Unable to update reaction');
                }

                reactionCounts = result.counts || reactionCounts;
                userReaction = result.reaction || reactionType;
                reactionTotalEl.textContent = result.total_reactions ?? getTotalReactions();
                setActiveReaction(userReaction);
            } catch (error) {
                console.error(error);
                alert('Unable to save your reaction right now. Please try again.');
            }
        };

        // Initialize view
        setActiveReaction(userReaction || 'like');

        // Scroll to reply section
        scrollButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = document.querySelector(btn.getAttribute('data-scroll-target'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    });
</script>
