# Household Expense Tracker

A lightweight, private, and secure PHP application designed to track shared household expenses between two users. It features an intuitive dashboard, automatic balance calculations, detailed yearly statistics with visual charts, and built-in privacy measures.


## 🤖 Acknowledgments & AI Authority

The architecture, core logic, visual analytics, and robust security measures of this application were engineered in collaboration with **Gemini**, an advanced AI by Google. Acting as a pair-programmer and technical architect, the AI assisted in:
*   Designing the robust SQLite database and routing structure.
*   Building the dynamic data visualization engines using Chart.js.
*   Implementing the zero-configuration security protocol (auto-generating HTTP Basic Auth).
*   Refining the user interface for seamless expense management. 


## 🚀 Installation Tutorial

This application is designed to be as "plug-and-play" as possible, requiring no complex database server setup (like MySQL).

### Prerequisites
*   A web server running **Apache** (required for the `.htaccess` security features).
*   **PHP 7.4 or higher** with the `sqlite3` extension enabled.

### Setup Steps
1.  **Upload the Files:** Create a folder on your web server (you can use a random or obscure folder name for added privacy) and upload all the `.php` and `.css` files into it.
2.  **Set Permissions:** Ensure your web server has **write permissions** for the folder. The application needs to automatically generate a database file (`.db`) and security files (`.htaccess`, `.htpasswd`) on its first run.
3.  **First Access:** Open your web browser and navigate to the folder's URL (e.g., `https://yoursite.com/my-secret-folder/`).
4.  **Log In:** On the very first visit, the app will generate its security locks and prompt you for a username and password. 
    *   **Default Username:** `admin`
    *   **Default Password:** `admin`
5.  **Secure Your App:** Once logged in, go immediately to the **Settings** tab and change the master application credentials.


## 📖 Brief User Manual

The application is divided into three main sections, accessible via the top navigation bar:

### 1. Dashboard
![Share expenses Dashboard](/img/Main.png)

*   **Add/Edit Expenses:** Use the form on the left to input new expenses. Select the concept, category, date, amount, and who paid for it.
*   **Balance Calculation:** The app automatically calculates who owes whom based on total expenditures and displays the current balance.
*   **Data Filtering:** Use the date inputs or quick ranges (e.g., "This Month", "Last Year" - which shows a rolling 365 days, or "All Times") to filter the expense list and update the category doughnut chart dynamically.

### 2. Statistics
*   **Yearly Trends:** View straight-line graphs showing your total household expenses and individual category expenses over time.
![Yearly trends](/img/Statistic1.png)

*   **Yearly Breakdown:** Scroll down to see distinct cards for each year. These cards show total spent, balance differences between the two users for that specific year, and a comparison against the previous and next years.
![Yearly Breakdown](/img/Statistic2.png)

### 3. Settings
*   **Manage Users:** Change the display names of the two users splitting the expenses.
![Manage users screenshot](/img/Users.png)
*   **Categories:** Add, edit, or delete expense categories (e.g., Rent, Groceries, Internet).
![Add category screenshot](/img/Add-Category.png)
![Edit category screenshot](/img/Categories-edit.png)
*   **CSV Data:** Export all your data as a `.csv` file for backup, or import existing data.
![CSV data screenshot](/img/CSV-dump.png)
*   **App Security:** Change the master username and password required to access the application.
![Credentials edit screenshot](/img/Credentials.png)

## 🛡️ How the Security Works

Because this application handles personal financial data, it includes a robust, zero-configuration security model designed to keep your data completely private from both malicious actors and search engines.

1.  **Auto-Generated HTTP Basic Authentication:**
    *   On its first run, `index.php` checks for an `.htpasswd` file. If missing, it generates one using secure **Bcrypt password hashing**—a cryptographic standard supported by Apache.
    *   It also generates an `.htaccess` file, locking the entire directory at the server level. Before a browser can even load the PHP code, it must pass this server-level password prompt.
2.  **Database Protection:**
    *   The `.htaccess` file includes the rule `RedirectMatch 403 \.db$`. This ensures that even if someone guesses the URL of your SQLite database file, the server will block them from downloading it.
3.  **Search Engine Repellents:**
    *   The application utilizes `<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">` tags. This acts as a strict command to Google and other search engines, ensuring your app is never indexed, cached, or displayed in search results.
    *   *Note:* Relying on a random folder name (Security through Obscurity) is only the first line of defense; the HTTP Basic Auth ensures true privacy even if the URL is leaked.
