.wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }

/* Group-style header (single colored area; sticky under dashboard top bar) */
.group-hero {
    margin-bottom: 1rem;
    position: sticky;
    top: 64px; /* sits under the sticky dashboard top bar */
    z-index: 98;
}
.group-cover {
    border-radius: var(--radius-lg);
    background:
        radial-gradient(1000px 260px at 10% 10%, rgba(255,255,255,0.25), transparent 60%),
        linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    position: relative;
    overflow: hidden;
    padding: 18px;
    color: #fff;
}
.group-cover::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.10), rgba(0,0,0,0.22));
}
.group-inner { position: relative; z-index: 2; }
.group-title-row { display:flex; gap: 14px; align-items: center; flex-wrap: wrap; }
.group-avatar {
    width: 64px; height: 64px;
    border-radius: 18px;
    background: rgba(255,255,255,0.16);
    border: 1px solid rgba(255,255,255,0.22);
    display:flex; align-items:center; justify-content:center;
    font-weight: 950;
    color: #fff;
    flex: 0 0 auto;
}
.group-title { margin: 0; font-size: 1.7rem; font-weight: 950; color: #fff; }
.group-meta { margin-top: 4px; color: rgba(255,255,255,0.88); font-weight: 850; }
.group-actions { margin-left: auto; display:flex; gap: 10px; flex-wrap: wrap; }
.hero-badges { display:flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.hero-badge {
    display:inline-flex;
    align-items:center;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,0.16);
    border: 1px solid rgba(255,255,255,0.22);
    font-weight: 900;
    font-size: 0.78rem;
    color: #fff;
}

/* Forum-like tabs under the colored header */
.community-nav { margin: 0 0 18px; }
.category-filters { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.category-btn {
    text-decoration: none;
    padding: 8px 16px;
    border: 2px solid var(--border-soft, #D8E2DD);
    background: var(--bg-card, #F8F9F7);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
    font-weight: 600;
    color: var(--text-primary);
}
.category-btn:hover,
.category-btn.active {
    background: var(--accent-primary, #7FAFA3);
    border-color: var(--accent-primary, #7FAFA3);
    color: #FFFFFF;
}
