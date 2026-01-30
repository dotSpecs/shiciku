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
