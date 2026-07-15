/*
 * Opaque anonymous visitor id, stored in a first-party cookie for a year.
 * Not tied to any account — purely lets the stats pipeline (ArticleView,
 * ArticleShare) tell a repeat visit from a genuinely new reader apart.
 */
export function getVisitorId() {
    const match = document.cookie.match(/(?:^|; )bb_visitor=([^;]+)/);
    if (match) {
        return decodeURIComponent(match[1]);
    }

    const id = (window.crypto && window.crypto.randomUUID)
        ? window.crypto.randomUUID()
        : Math.random().toString(36).slice(2) + Date.now().toString(36);

    const oneYearInSeconds = 60 * 60 * 24 * 365;
    document.cookie = `bb_visitor=${encodeURIComponent(id)}; path=/; max-age=${oneYearInSeconds}; samesite=lax`;

    return id;
}
