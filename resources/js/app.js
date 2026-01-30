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
        return text
            .replace(/\u200B/g, "") // zero-width space
            .replace(/\uFEFF/g, "") // BOM
            .replace(/\s/g, "") // spaces
            .trim();
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
    // Since poemData.content is HTML (e.g., <p>...</p>), we process the DOM nodes
    // to preserve structure, but clean the text content of each paragraph.
    const paragraphs = contentEl.querySelectorAll("p");

    if (paragraphs.length > 0) {
        paragraphs.forEach((p) => {
            // Get text from the paragraph (strips tags)
            let text = p.innerText;
            let cleaned = cleanText(text);

            if (cleaned) {
                p.innerHTML = html(cleaned, { wrapNonChinese: true });
            }
        });
    } else {
        // Fallback if no P tags found
        let text = contentEl.innerText;
        let cleaned = cleanText(text);
        contentEl.innerHTML = html(cleaned, { wrapNonChinese: true }).replace(
            /\n/g,
            "<br>",
        );
    }

    contentEl.dataset.pinyinActive = "true";
};
