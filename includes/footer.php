<style>
    /* Footer Specific Styles */
    .app-footer {
        margin-top: auto; /* Pushes footer to bottom if page content is short */
        padding: 20px 0;
        background: rgba(255, 255, 255, 0.5); /* Glass effect base */
        backdrop-filter: blur(10px); /* Glass blur effect */
        border-top: 1px solid rgba(255, 255, 255, 0.3); /* Subtle top border */
        text-align: center;
        font-size: 0.9rem;
        color: #6c757d; /* Bootstrap text-muted color */
        position: relative;
        z-index: 10;
    }

    .app-footer strong {
        color: #333;
    }

    .app-footer a {
        color: var(--bs-primary); /* Uses Bootstrap primary color */
        text-decoration: none;
        font-weight: bold;
        transition: color 0.3s ease;
    }

    .app-footer a:hover {
        color: #0a58ca; /* Darker blue on hover */
        text-decoration: underline;
    }

    /* Little heartbeat animation for the love icon */
    .footer-heart {
        color: #dc3545; /* Bootstrap danger red */
        display: inline-block;
        animation: footerHeartbeat 1.5s infinite;
    }

    @keyframes footerHeartbeat {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.15); }
    }
</style>

<footer class="app-footer">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <p class="mb-1">© 2006-<?= date('Y') ?> Copyright <strong>Family Care & Love</strong></p>

                <small>
                    Another product made with <i class="fas fa-heart footer-heart mx-1"></i> by
                    <a href="https://www.airevo.my" target="_blank" rel="noopener noreferrer">www.airevo.my</a>
                </small>
            </div>
        </div>
    </div>
</footer>
