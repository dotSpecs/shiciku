import "./bootstrap";
import "preline";

// This code should be added to <head>.
// It's used to prevent page load glitches.
const html = document.querySelector("html");
const isLightOrAuto =
    localStorage.getItem("hs_theme") === "light" ||
    (localStorage.getItem("hs_theme") === "auto" &&
        !window.matchMedia("(prefers-color-scheme: dark)").matches);
const isDarkOrAuto =
    localStorage.getItem("hs_theme") === "dark" ||
    (localStorage.getItem("hs_theme") === "auto" &&
        window.matchMedia("(prefers-color-scheme: dark)").matches);

if (isLightOrAuto && html.classList.contains("dark"))
    html.classList.remove("dark");
else if (isDarkOrAuto && html.classList.contains("light"))
    html.classList.remove("light");
else if (isDarkOrAuto && !html.classList.contains("dark"))
    html.classList.add("dark");
else if (isLightOrAuto && !html.classList.contains("light"))
    html.classList.add("light");

window.togglePinyin = async () => {
    // Check if we are on a page with poem data
    if (!window.poemData) return;

    const titleEl = document.getElementById("poem-title");
    const dynastyEl = document.getElementById("poem-dynasty");
    const authorEl = document.getElementById("poem-author");
    const contentEl = document.getElementById("poem-content");

    // Use a dataset attribute on the content element to track state
    // We could also use a global variable, but this is scoped to the element
    const isActive = contentEl.dataset.pinyinActive === "true";

    if (isActive) {
        // Restore original content
        if (titleEl) titleEl.textContent = window.poemData.title;
        if (dynastyEl) dynastyEl.textContent = window.poemData.dynasty;
        if (authorEl) authorEl.textContent = window.poemData.author;
        if (contentEl) contentEl.innerHTML = window.poemData.content;

        delete contentEl.dataset.pinyinActive;
        return;
    }

    // Load module if needed
    if (!window.pinyinPro) {
        await import("./pinyin.js");
    }

    const { html } = window.pinyinPro;

    // Helper to clean text similar to reference logic
    const cleanText = (text) => {
        return (
            text
                .replace(/\u200B/g, "") // zero-width space
                .replace(/\uFEFF/g, "") // BOM
                // Replace all whitespace EXCEPT newlines
                .replace(/[^\S\n]/g, "")
                .trim()
        );
    };

    // Convert Title
    if (titleEl) {
        titleEl.innerHTML = html(cleanText(window.poemData.title), {
            wrapNonChinese: true,
        });
    }

    // Convert Dynasty
    if (dynastyEl) {
        dynastyEl.innerHTML = html(cleanText(window.poemData.dynasty), {
            wrapNonChinese: true,
        });
    }

    // Convert Author
    if (authorEl) {
        authorEl.innerHTML = html(cleanText(window.poemData.author), {
            wrapNonChinese: true,
        });
    }

    // Convert Content
    // Use window.poemData.content directly as source of truth
    let contentHtml = window.poemData.content;

    // Replace <p> endings and <br> with newlines to preserve structure
    // We treat </p> as a double newline (paragraph break) and <br> as single newline
    let textWithBreaks = contentHtml
        .replace(/<\/p>/gi, "\n\n")
        .replace(/<br\s*\/?>/gi, "\n")
        .replace(/<[^>]+>/g, ""); // Strip all other tags

    // Clean text (remove special chars)
    let cleaned = cleanText(textWithBreaks);

    // Normalize newlines: limit to max 2 consecutive newlines to avoid excessive spacing
    // This handles cases where source HTML has newlines between P tags
    cleaned = cleaned.replace(/\n{3,}/g, "\n\n");

    // Generate pinyin HTML manually to ensure newlines are preserved
    // Split by newline and map each line to pinyin html
    let lines = cleaned.split("\n");
    let pinyinLines = lines.map((line) => {
        // If line is empty (e.g. from \n\n), return empty string to create a blank line via join
        if (!line) return "";
        return html(line, { wrapNonChinese: true });
    });

    // Join with <br> to restore visual line breaks
    let pinyinHtml = pinyinLines.join("<br>");

    contentEl.innerHTML = pinyinHtml;

    contentEl.dataset.pinyinActive = "true";
};

/**
 * Common Audio Player Logic
 */
window.AudioPlayerManager = class {
    constructor() {
        this.isLoading = false;
        this.isLoaded = false;
        this.currentUrl = null;
    }

    async play(
        apiUrl,
        buttonId = "readAloudBtn",
        containerId = "audioPlayerContainer",
        playerId = "audioPlayer",
    ) {
        if (this.isLoading || this.isLoaded) return;

        const btn = document.getElementById(buttonId);
        const playerContainer = document.getElementById(containerId);
        const player = document.getElementById(playerId);

        if (!btn || !playerContainer || !player) {
            console.error("Audio player elements not found");
            return;
        }

        this.isLoading = true;
        const originalText = btn.textContent;
        btn.textContent = "获取中...";
        btn.classList.add("cursor-not-allowed", "opacity-50");

        try {
            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": token,
                },
            });

            const data = await response.json();

            if (data.status === "success" && data.body) {
                const audioBlob = this.base64ToBlob(data.body, "audio/mpeg");
                const audioUrl = URL.createObjectURL(audioBlob);

                player.src = audioUrl;
                playerContainer.style.display = "block";
                player.play();

                this.isLoaded = true;
                this.currentUrl = apiUrl;
                btn.textContent = "已加载";
            } else {
                throw new Error(data.message || "未知错误");
            }
        } catch (error) {
            console.error("Error fetching audio:", error);
            alert("获取音频失败：" + error.message);
            btn.textContent = originalText;
            btn.classList.remove("cursor-not-allowed", "opacity-50");
            this.isLoading = false; // Reset loading state on error
        } finally {
            if (this.isLoaded) {
                this.isLoading = false;
            }
        }
    }

    reset() {
        this.isLoading = false;
        this.isLoaded = false;
        // Note: We might want to reset the button state here if needed, but usually reset happens on page navigation
    }

    close(containerId = "audioPlayerContainer", playerId = "audioPlayer") {
        const playerContainer = document.getElementById(containerId);
        const player = document.getElementById(playerId);
        if (player) {
            player.pause();
            player.currentTime = 0;
        }
        if (playerContainer) {
            playerContainer.style.display = "none";
        }
    }

    base64ToBlob(base64, mimeType) {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type: mimeType });
    }
};

// Initialize global instance
window.audioPlayerManager = new window.AudioPlayerManager();
window.handleReadAloud = (apiUrl) => window.audioPlayerManager.play(apiUrl);
window.closeAudioPlayer = () => window.audioPlayerManager.close();
