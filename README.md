# 🎓 Premium WP LMS Modules (Video & E-Book Academy)

A high-performance, custom-built Learning Management System (LMS) plugin for WordPress. Designed specifically for competitive exam prep (like AFCAT and ACC), this plugin transforms any standard WordPress site into a premium, Fort Knox-secured digital academy.

It features lightning-fast video playlists, a highly secure multi-PDF document library, smart-bookmarking, and seamless WooCommerce integration for granular access control.

## ✨ Core Features

### 🎬 1. Smart Video Playlists (`[course_playlist id="X"]`)
A Netflix-style video interface built for deep learning.
* **Instant Video Switching:** Uses a hidden-div pre-render architecture (no AJAX loading screens) to instantly swap lessons.
* **Smart Embed Engine:** Paste a raw YouTube link or a Presto Player ID, and the system automatically outputs the correct native player.
* **Private Timestamped Notes:** Students can take private notes directly next to the video. Clicking the ⏱️ button automatically drops the current video timestamp into their notes for 1-click reviewing later.
* **Time-Based Content Dripping:** Lock future video modules based on how many days have passed since the student purchased the course.
* **Auto-Advance & Gamification:** Auto-plays the next video in 3 seconds, tracks completion progress, and awards a certificate at 100%.
* **UX Toggles:** Built-in Theater Mode (⛶) and Dark Mode (🌙) toggles.

### 📚 2. Secure E-Book Library (`[ebook_reader id="X"]`)
A "Fort Knox" secure PDF reader built on PDF.js, designed to protect your intellectual property.
* **Two-Tier Hybrid Architecture:** Upload multiple PDFs into a single library. Creates a beautiful Accordion Sidebar where students can switch between documents (e.g., Math Notes vs. English Notes) and jump to specific chapters.
* **Dynamic Anti-Piracy Watermarking:** Faintly overlays the logged-in student's Name and Email diagonally across every page. If they screenshot and share it, the leak is tied to them.
* **Granular Download Permissions:** By default, all PDFs are strictly View-Only (right-click disabled). Admins can go to a specific user's WooCommerce Order and check a `[x] Grant PDF Download Rights` box to selectively unlock offline access for specific students.
* **Advanced Mobile Physics:** Custom multi-touch engine allows native pinch-to-zoom on mobile devices, with smart swipe-to-turn-page functionality that disables itself when zoomed in to allow panning.
* **Multi-Dimensional Memory:** Saves independent page bookmarks for *every single PDF* in the bundle.

### 🏛️ 3. Global Study Hall (`[cppm_study_hall]`)
A dashboard shortcode that gives students a premium entry point. With one click of the "Continue Studying" button, it drops them directly into the exact video timestamp or PDF page they left off on the night before.

---

## 🚀 Installation & Setup

1. Download the plugin folder and upload it to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure **WooCommerce** is installed and active (required for course access control).
4. *(Optional but Recommended)* Install **Presto Player** for highly secure, optimized video playback.

---

## 🛠️ How to Use

### Creating a Video Course
1. Go to **Course Playlists > Add New Playlist**.
2. Select the required WooCommerce Product from the dropdown (this acts as the paywall).
3. Add your Module Titles, Video URLs (YouTube or Presto IDs), and Drip Delays.
4. Publish and copy the shortcode: `[course_playlist id="123"]`

### Creating an E-Book Library
1. Go to **Secure E-Books > Add New E-Book**.
2. Select the required WooCommerce Product.
3. Click **+ Add New PDF Document** to upload a file via the native WP Media Library.
4. Add specific Chapters and Page Numbers to create the Accordion Index.
5. Publish and copy the shortcode: `[ebook_reader id="123"]`

### Overriding PDF Download Rights
If a student requests offline access to a locked PDF:
1. Go to **WooCommerce > Orders** and open their specific order.
2. Scroll to the **Custom LMS Permissions** box.
3. Check **Grant PDF Download Rights** and save. The next time they log in, a green Download button will appear in their reader toolbar.

---

## 🎨 UI Customization

The plugin includes a dedicated **UI Settings** panel under the Course Playlists menu.
* **Brand Primary Color:** Change the accent color for progress bars, buttons, and active states.
* **Custom CSS:** Add your own CSS directly from the dashboard to override theme constraints (fully compatible with Astra and Elementor).

---

## 🔒 Security Architecture
* **Direct File Protection:** E-Books are rendered via HTML5 Canvas using PDF.js. The raw PDF file is never exposed to the browser's native PDF viewer.
* **AJAX Verification:** All progress tracking, notes saving, and video switching is verified against WordPress Nonces and user session IDs to prevent spoofing.
* **Astra Theme Resets:** Includes aggressive CSS resets to ensure Astra's global container paddings do not break the immersive full-screen reading and watching experience.
