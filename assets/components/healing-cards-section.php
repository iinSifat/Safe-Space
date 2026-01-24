<?php
/**
 * Everything You Need to Heal - Auto-Scrolling Cards Component
 * Reusable component for displaying service/feature cards with right-to-left auto-scroll
 * 
 * Usage: <?php include 'assets/components/healing-cards-section.php'; ?>
 */
?>

<section class="healing-section" id="healing-features">
    <div class="healing-container">
        <!-- Section Header -->
        <div class="healing-header">
            <h2 class="healing-title">Everything You Need to Heal</h2>
            <p class="healing-subtitle">Comprehensive tools and support for your mental wellness journey</p>
            <button class="view-all-btn" id="viewAllBtn">View All Features</button>
        </div>

        <!-- Auto-Scrolling Cards Container -->
        <div class="cards-wrapper" id="cardsWrapper">
            <div class="cards-scroll" id="cardsScroll">
                <!-- Card 1: Anonymous Sharing -->
                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <circle cx="9" cy="10" r="1" fill="currentColor"></circle>
                            <circle cx="12" cy="10" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="10" r="1" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h3 class="card-title">Anonymous Sharing</h3>
                    <p class="card-description">Share your thoughts and experiences safely without revealing your identity</p>
                    <div class="card-footer">
                        <span class="card-tag">Safe & Private</span>
                    </div>
                </div>

                <!-- Card 2: Peer Support -->
                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Peer Support</h3>
                    <p class="card-description">Connect with trained peer supporters who understand your challenges</p>
                    <div class="card-footer">
                        <span class="card-tag">Community Care</span>
                    </div>
                </div>

                <!-- Card 3: Group Chats -->
                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <path d="M9 10h.01"></path>
                            <path d="M12 10h.01"></path>
                            <path d="M15 10h.01"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Group Chats</h3>
                    <p class="card-description">Join supportive communities with people facing similar experiences</p>
                    <div class="card-footer">
                        <span class="card-tag">Social Support</span>
                    </div>
                </div>

                <!-- Card 4: Resource Library -->
                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            <path d="M9 9h2"></path>
                            <path d="M9 13h2"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Resource Library</h3>
                    <p class="card-description">Access curated articles, guides, and educational materials</p>
                    <div class="card-footer">
                        <span class="card-tag">Knowledge Hub</span>
                    </div>
                </div>

                <!-- Card 5: Guided Programs -->
                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <h3 class="card-title">Guided Programs</h3>
                    <p class="card-description">Follow structured wellness programs designed by mental health experts</p>
                    <div class="card-footer">
                        <span class="card-tag">Expert Guidance</span>
                    </div>
                </div>

                <!-- Card 6: Mood Tracking -->
                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                            <circle cx="9" cy="9" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="9" r="1" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h3 class="card-title">Mood Tracking</h3>
                    <p class="card-description">Monitor your emotional patterns and celebrate your progress</p>
                    <div class="card-footer">
                        <span class="card-tag">Self-Insight</span>
                    </div>
                </div>

                <!-- Duplicate cards for seamless infinite loop -->
                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <circle cx="9" cy="10" r="1" fill="currentColor"></circle>
                            <circle cx="12" cy="10" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="10" r="1" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h3 class="card-title">Anonymous Sharing</h3>
                    <p class="card-description">Share your thoughts and experiences safely without revealing your identity</p>
                    <div class="card-footer">
                        <span class="card-tag">Safe & Private</span>
                    </div>
                </div>

                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Peer Support</h3>
                    <p class="card-description">Connect with trained peer supporters who understand your challenges</p>
                    <div class="card-footer">
                        <span class="card-tag">Community Care</span>
                    </div>
                </div>

                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <path d="M9 10h.01"></path>
                            <path d="M12 10h.01"></path>
                            <path d="M15 10h.01"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Group Chats</h3>
                    <p class="card-description">Join supportive communities with people facing similar experiences</p>
                    <div class="card-footer">
                        <span class="card-tag">Social Support</span>
                    </div>
                </div>

                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            <path d="M9 9h2"></path>
                            <path d="M9 13h2"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Resource Library</h3>
                    <p class="card-description">Access curated articles, guides, and educational materials</p>
                    <div class="card-footer">
                        <span class="card-tag">Knowledge Hub</span>
                    </div>
                </div>

                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <h3 class="card-title">Guided Programs</h3>
                    <p class="card-description">Follow structured wellness programs designed by mental health experts</p>
                    <div class="card-footer">
                        <span class="card-tag">Expert Guidance</span>
                    </div>
                </div>

                <div class="healing-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                            <circle cx="9" cy="9" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="9" r="1" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h3 class="card-title">Mood Tracking</h3>
                    <p class="card-description">Monitor your emotional patterns and celebrate your progress</p>
                    <div class="card-footer">
                        <span class="card-tag">Self-Insight</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- View All Grid (Hidden by default) -->
        <div class="all-cards-grid" id="allCardsGrid" style="display: none;">
            <div class="healing-card-static">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <circle cx="9" cy="10" r="1" fill="currentColor"></circle>
                            <circle cx="12" cy="10" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="10" r="1" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h3 class="card-title">Anonymous Sharing</h3>
                    <p class="card-description">Share your thoughts and experiences safely without revealing your identity</p>
                    <div class="card-footer">
                        <span class="card-tag">Safe & Private</span>
                    </div>
                </div>

                <div class="healing-card-static">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Peer Support</h3>
                    <p class="card-description">Connect with trained peer supporters who understand your challenges</p>
                    <div class="card-footer">
                        <span class="card-tag">Community Care</span>
                    </div>
                </div>

                <div class="healing-card-static">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <path d="M9 10h.01"></path>
                            <path d="M12 10h.01"></path>
                            <path d="M15 10h.01"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Group Chats</h3>
                    <p class="card-description">Join supportive communities with people facing similar experiences</p>
                    <div class="card-footer">
                        <span class="card-tag">Social Support</span>
                    </div>
                </div>

                <div class="healing-card-static">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            <path d="M9 9h2"></path>
                            <path d="M9 13h2"></path>
                        </svg>
                    </div>
                    <h3 class="card-title">Resource Library</h3>
                    <p class="card-description">Access curated articles, guides, and educational materials</p>
                    <div class="card-footer">
                        <span class="card-tag">Knowledge Hub</span>
                    </div>
                </div>

                <div class="healing-card-static">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </div>
                    <h3 class="card-title">Guided Programs</h3>
                    <p class="card-description">Follow structured wellness programs designed by mental health experts</p>
                    <div class="card-footer">
                        <span class="card-tag">Expert Guidance</span>
                    </div>
                </div>

                <div class="healing-card-static">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                            <circle cx="9" cy="9" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="9" r="1" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h3 class="card-title">Mood Tracking</h3>
                    <p class="card-description">Monitor your emotional patterns and celebrate your progress</p>
                    <div class="card-footer">
                        <span class="card-tag">Self-Insight</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Healing Cards Section Styles */
.healing-section {
    padding: 4rem 2rem;
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.03), rgba(123, 93, 255, 0.03));
    position: relative;
    overflow: hidden;
}

.healing-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Styles */
.healing-header {
    text-align: center;
    margin-bottom: 3rem;
}

.healing-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
    font-family: var(--font-family);
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.healing-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0 0 1.5rem 0;
    font-family: var(--font-family);
}

.view-all-btn {
    padding: 12px 28px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border: none;
    border-radius: 25px;
    font-size: 0.95rem;
    font-weight: 600;
    font-family: var(--font-family);
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
}

.view-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(20, 184, 166, 0.4);
}

/* Cards Container */
.cards-wrapper {
    position: relative;
    overflow: hidden;
    padding: 2rem 0;
    width: 100%;
}

.cards-scroll {
    display: flex;
    gap: 2rem;
    will-change: transform;
    position: relative;
    transition: transform 0.5s ease-out;
}

/* Sliding Animation */
@keyframes slide-cards {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}

.cards-scroll:hover {
    animation-play-state: paused;
}

.cards-scroll {
    animation: slide-cards 100s linear infinite;
}

.cards-scroll.manual-control {
    animation: none;
}

/* Card Styles */
.healing-card {
    flex: 0 0 auto;
    width: 320px;
    background: var(--bg-card, #F8F9F7);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    scroll-snap-align: start;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.healing-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.1), rgba(123, 93, 255, 0.1));
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 1;
}

.healing-card:hover::before {
    opacity: 1;
}

.healing-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(20, 184, 166, 0.2);
    animation: gentle-blink 2s ease-in-out infinite;
}

/* Gentle Blink Animation (Smooth Opacity) */
@keyframes gentle-blink {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}

/* Smooth Glow on Hover */
@keyframes glow-effect {
    0%, 100% {
        box-shadow: 0 20px 40px rgba(20, 184, 166, 0.2);
    }
    50% {
        box-shadow: 0 20px 50px rgba(20, 184, 166, 0.35);
    }
}

.healing-card:hover {
    animation: gentle-blink 2s ease-in-out infinite, glow-effect 3s ease-in-out infinite;
}

/* Card Content */
.card-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    color: white;
    position: relative;
    z-index: 2;
}

.card-icon svg {
    width: 32px;
    height: 32px;
}

.card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    font-family: var(--font-family);
    position: relative;
    z-index: 2;
}

.card-description {
    font-size: 0.95rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0 0 1.5rem 0;
    font-family: var(--font-family);
    position: relative;
    z-index: 2;
}

.card-footer {
    display: flex;
    gap: 0.5rem;
    position: relative;
    z-index: 2;
}

.card-tag {
    display: inline-block;
    padding: 6px 12px;
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.15), rgba(123, 93, 255, 0.15));
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: var(--font-family);
}

/* All Cards Grid View */
.all-cards-grid {
    display: none;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
    width: 100%;
    padding: 1rem 0;
}

.all-cards-grid[style*="display: grid"] {
    display: grid !important;
}

.healing-card-static {
    background: var(--bg-card, #F8F9F7);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    min-height: 300px;
}

.healing-card-static:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(20, 184, 166, 0.2);
}

.healing-card-static .card-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    color: white;
}

.healing-card-static .card-icon svg {
    width: 32px;
    height: 32px;
}

.healing-card-static .card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    font-family: var(--font-family);
}

.healing-card-static .card-description {
    font-size: 0.95rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0 0 1.5rem 0;
    font-family: var(--font-family);
}

.healing-card-static .card-footer {
    display: flex;
    gap: 0.5rem;
}

.healing-card-static .card-tag {
    display: inline-block;
    padding: 6px 12px;
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.15), rgba(123, 93, 255, 0.15));
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: var(--font-family);
}

/* Scroll Buttons - Hidden */
.scroll-btn {
    display: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .healing-section {
        padding: 3rem 1rem;
    }

    .healing-title {
        font-size: 1.8rem;
    }

    .healing-subtitle {
        font-size: 0.9rem;
    }

    .healing-card {
        width: 280px;
        padding: 1.5rem;
    }

    .card-icon {
        width: 50px;
        height: 50px;
    }

    .card-icon svg {
        width: 26px;
        height: 26px;
    }

    .card-title {
        font-size: 1.1rem;
    }

    .card-description {
        font-size: 0.9rem;
    }

    .scroll-btn {
        width: 40px;
        height: 40px;
    }

    .scroll-btn svg {
        width: 20px;
        height: 20px;
    }
}

@media (max-width: 480px) {
    .healing-section {
        padding: 2rem 1rem;
    }

    .healing-title {
        font-size: 1.5rem;
    }

    .healing-subtitle {
        font-size: 0.85rem;
    }

    .healing-card {
        width: 240px;
        padding: 1.25rem;
    }

    .card-icon {
        width: 45px;
        height: 45px;
        margin-bottom: 1rem;
    }

    .card-icon svg {
        width: 22px;
        height: 22px;
    }

    .card-title {
        font-size: 1rem;
    }

    .card-description {
        font-size: 0.85rem;
    }

    .scroll-btn {
        display: none;
    }
}

/* Auto-Scroll Animation */
.cards-scroll {
    scroll-behavior: auto;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardsScroll = document.getElementById('cardsScroll');
    const cardsWrapper = document.getElementById('cardsWrapper');
    const allCardsGrid = document.getElementById('allCardsGrid');
    const viewAllBtn = document.getElementById('viewAllBtn');
    const healingCards = document.querySelectorAll('.healing-card');
    
    if (!cardsScroll) return;

    const cardWidth = 320;
    const gap = 32;
    const cardStep = cardWidth + gap;
    const totalCards = 6;
    
    let currentPosition = 0;
    let animationRunning = true;
    let animationId;
    let isGridView = false;

    // Auto-scroll animation (faster speed)
    function autoScroll() {
        if (!animationRunning || isGridView) return;
        
        currentPosition += 100; // Ultra-fast - 50 pixels per frame (3000px/sec at 60fps)
        
        const totalWidth = cardStep * totalCards;
        
        if (currentPosition >= totalWidth) {
            currentPosition = 0;
        }
        
        cardsScroll.style.transition = 'none';
        cardsScroll.style.transform = `translateX(-${currentPosition}px)`;
        animationId = requestAnimationFrame(autoScroll);
    }

    // Toggle between carousel and grid view
    if (viewAllBtn) {
        console.log('View All button found!');
        viewAllBtn.addEventListener('click', function() {
            console.log('View All button clicked! Current view:', isGridView ? 'Grid' : 'Carousel');
            
            isGridView = !isGridView;
            
            if (isGridView) {
                console.log('Switching to GRID view');
                // Show grid view
                animationRunning = false;
                cancelAnimationFrame(animationId);
                cardsWrapper.style.display = 'none';
                allCardsGrid.style.display = 'grid';
                viewAllBtn.textContent = 'Show Carousel';
                
                console.log('Grid display set to:', allCardsGrid.style.display);
                console.log('Cards wrapper display:', cardsWrapper.style.display);
                console.log('Grid cards count:', document.querySelectorAll('.healing-card-static').length);
                
                // Scroll to grid if needed
                setTimeout(() => {
                    allCardsGrid.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            } else {
                console.log('Switching to CAROUSEL view');
                // Show carousel view
                allCardsGrid.style.display = 'none';
                cardsWrapper.style.display = 'block';
                animationRunning = true;
                autoScroll();
                viewAllBtn.textContent = 'View All Features';
            }
        });
    } else {
        console.error('View All button NOT FOUND!');
    }

    // Start auto-scroll
    autoScroll();

    // Pause on hover
    cardsScroll.addEventListener('mouseenter', function() {
        animationRunning = false;
        cancelAnimationFrame(animationId);
    });

    cardsScroll.addEventListener('mouseleave', function() {
        if (!isGridView) {
            animationRunning = true;
            autoScroll();
        }
    });

    // Make cards clickable to highlight them
    healingCards.forEach(card => {
        card.addEventListener('click', function(e) {
            e.stopPropagation();
            healingCards.forEach(c => c.style.outline = 'none');
            this.style.outline = '3px solid var(--primary-color)';
            this.style.outlineOffset = '8px';
            setTimeout(() => {
                this.style.outline = 'none';
            }, 2000);
        });
    });

    // Pause animation on touch for mobile
    cardsScroll.addEventListener('touchstart', function() {
        animationRunning = false;
        cancelAnimationFrame(animationId);
    });

    cardsScroll.addEventListener('touchend', function() {
        if (!isGridView) {
            setTimeout(() => {
                animationRunning = true;
                autoScroll();
            }, 2000);
        }
    });

    // Add smooth scroll to Features link in navigation
    const allFeatureLinks = document.querySelectorAll('a[href*="features"]');
    allFeatureLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && (href.includes('healing') || href === '#features')) {
                e.preventDefault();
                const healingSection = document.getElementById('healing-features');
                if (healingSection) {
                    healingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });
});
</script>
