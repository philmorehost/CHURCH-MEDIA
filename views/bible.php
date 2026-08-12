<?php
/**
 * Bible Page View
 */
// We assume the page is rendered via the Router and has access to global helpers
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-center mb-5">
                <h1 class="display-4">Holy Bible</h1>
                <p class="lead">Explore the Word of God</p>
            </div>

            <!-- Bible Controls -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Version</label>
                            <select id="bible-version" class="form-select">
                                <option value="KJV">King James Version (KJV)</option>
                                <option value="NIV">New International Version (NIV)</option>
                                <option value="NLT">New Living Translation (NLT)</option>
                                <option value="NKJV">New King James Version (NKJV)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Language</label>
                            <select id="bible-lang" class="form-select">
                                <option value="en">English</option>
                                <option value="es">Spanish</option>
                                <option value="fr">French</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Book</label>
                            <select id="bible-book" class="form-select">
                                <option value="Genesis">Genesis</option>
                                <option value="Exodus">Exodus</option>
                                <option value="Leviticus">Leviticus</option>
                                <option value="Numbers">Numbers</option>
                                <option value="Deuteronomy">Deuteronomy</option>
                                <!-- Other books would be populated dynamically or listed here -->
                                <option value="John">John</option>
                                <option value="Romans">Romans</option>
                                <option value="Revelation">Revelation</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Chapter</label>
                            <input type="number" id="bible-chapter" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button id="btn-read" class="btn btn-primary w-100">Read</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scripture Display -->
            <div id="bible-content" class="bible-text-container p-4 bg-white rounded shadow-sm" style="min-height: 400px;">
                <div class="text-center text-muted mt-5">
                    <p>Select a book and chapter to start reading.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bible-text-container {
        line-height: 1.8;
        font-size: 1.1rem;
    }
    .bible-verse {
        margin-bottom: 0.5rem;
    }
    .verse-num {
        font-weight: bold;
        font-size: 0.8rem;
        color: #6c757d;
        margin-right: 0.5rem;
        vertical-align: super;
    }
</style>

<script>
document.getElementById('btn-read').addEventListener('click', async () => {
    const book = document.getElementById('bible-book').value;
    const chapter = document.getElementById('bible-chapter').value;
    const version = document.getElementById('bible-version').value;
    const lang = document.getElementById('bible-lang').value;
    const contentDiv = document.getElementById('bible-content');

    contentDiv.innerHTML = '<div class="text-center mt-5"><div class="spinner-border text-primary" role="status"></div><p>Loading scripture...</p></div>';

    try {
        const response = await fetch(`/api/bible.php?book=${book}&chapter=${chapter}&version=${version}&lang=${lang}`);
        const data = await response.json();

        if (data.error) {
            contentDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            return;
        }

        let html = `<h2 class="text-center mb-4">${book} ${chapter}</h2>`;
        
        // Handle different API responses (Bible-Api.com vs API.Bible)
        if (data.verses) {
            // Bible-Api.com format
            data.verses.forEach(v => {
                html += `<div class="bible-verse"><span class="verse-num">${v.verse}</span>${v.text}</div>`;
            });
        } else if (data.content) {
            // API.Bible format usually returns HTML or separate verses
            html += data.content;
        } else {
            html += '<p class="text-center">No content found for this selection.</p>';
        }

        contentDiv.innerHTML = html;
    } catch (e) {
        contentDiv.innerHTML = `<div class="alert alert-danger">An error occurred while fetching the Bible text. Please try again.</div>`;
    }
});
</script>
