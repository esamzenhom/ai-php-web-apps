# Welcome to App Studio

Build your own website by chatting with AI. This guide takes you from getting the
project onto your computer to creating your private administrator account,
connecting an AI assistant, and making your first request.

You do not need to write code. Some computers need a few one-time preparation
commands; the steps below explain where to enter them. Once set up, you use the
browser and the Start/Stop launchers.

## Follow the guide

1. [Get the project](#1-get-the-project-onto-your-computer)
2. [Start the app](#2-start-the-app)
3. [Create your administrator account](#3-create-your-administrator-account)
4. [Protect your account and save recovery information](#4-protect-your-admin-account)
5. [Connect an AI assistant](#5-connect-an-ai-assistant)
6. [Finish dashboard preferences](#6-finish-your-dashboard-preferences)
7. [Send your first request](#7-send-your-first-building-request)
8. [Stop safely](#8-stop-the-app-safely)
9. [Return later](#9-come-back-later)

## Before you begin

Have these ready:

- A Mac, a Windows computer with WSL2, or an Ubuntu/Debian Linux computer.
- An internet connection for the first installation and AI requests.
- Permission to install software on your computer. On a work computer, ask your
  IT administrator if installation is restricted.
- An AI account with API access or supported Codex/Claude Code access. You can
  create the admin account before connecting an AI provider.
- A safe place to save your administrator password and recovery information.

**Docker** runs the app's supporting services on your computer. The launcher can
install it on supported systems, but you must complete any installer, permission
or terms prompts yourself. **Python 3** runs the startup helpers. **Git** downloads
the project while preserving the links between its shared assistant instructions.

Starting the app makes it available on your own computer. It does **not** publish
your website to the internet. Publishing this platform's source on GitHub is also
separate from publishing the website you build with it.

## 1. Get the project onto your computer

Follow only the instructions for your computer. Keep the project in a permanent
local folder. Your website and saved information will live inside it, so do not
use a temporary folder or delete it after installation.

### Mac

1. Install Python 3 using the installer from the
   [official Python macOS downloads page](https://www.python.org/downloads/macos/).
2. Open **Terminal**: press **Command + Space**, type **Terminal**, then press Enter.
3. Type `git --version` and press Enter. If macOS offers to install its command-line
   tools, finish that installation, then reopen Terminal and try the command again.
4. Copy these lines into Terminal, one line at a time, pressing Enter after each:

   ```sh
   cd ~/Documents
   git clone https://github.com/esamzenhom/ai-php-web-apps.git
   ```

5. Wait until the download finishes and Terminal lets you type again. In Finder,
   open **Documents → ai-php-web-apps**. You should see this README, an
   **application** folder and a **Start and Stop** folder.
6. Continue to **Step 2** below.

If Terminal says the destination already exists, open that existing folder. Do not
delete it or replace it: it may already contain your work.

### Windows

Windows uses **WSL2**, a Linux environment inside Windows. This app must live in
that Linux environment's home folder. Saving it under `C:\`, Desktop, Downloads
or OneDrive is not supported by the Windows launcher.

1. If you do not have WSL2, open the Start menu, search for **PowerShell**, right-click
   it, and choose **Run as administrator**. Enter:

   ```powershell
   wsl --install
   ```

2. Restart if Windows requests it. Open **Ubuntu** from the Start menu and finish
   creating its Linux username and password. These are for Ubuntu; you will create
   a separate App Studio administrator account later. See
   [Microsoft's WSL installation guide](https://learn.microsoft.com/en-us/windows/wsl/install)
   if installation does not complete.
3. In the **Ubuntu window**, enter the following lines one at a time. They install
   Git and Python, then download the project into your Linux home folder:

   ```sh
   sudo apt update
   sudo apt install -y git python3
   cd ~
   git clone https://github.com/esamzenhom/ai-php-web-apps.git
   ```

   If asked for a password, enter the Ubuntu password you just created. The
   terminal may show no characters while you type; this is normal.
4. Open Windows **File Explorer**. In its address bar enter
   `\\wsl.localhost\Ubuntu\home`, then open your Linux username's folder and
   **ai-php-web-apps**. If your distribution has a different name, use that name
   instead of `Ubuntu`.
5. Continue to **Step 2** and run the Windows Start launcher. It can install and
   open Docker Desktop. Complete Docker's setup screens.
6. In Docker Desktop, open **Settings → Resources → WSL Integration**, enable your
   Ubuntu distribution, and apply the change. If startup stopped to request this,
   run **Start.cmd** again. See the
   [official Docker WSL guide](https://docs.docker.com/desktop/features/wsl/).

Install Python inside Ubuntu as shown above; installing only the Windows version
of Python does not satisfy this app's startup requirement.

### Ubuntu or Debian Linux

1. Open **Terminal** from your applications menu.
2. Enter these lines one at a time, pressing Enter after each:

   ```sh
   sudo apt update
   sudo apt install -y git python3
   cd ~
   git clone https://github.com/esamzenhom/ai-php-web-apps.git
   ```

3. Enter your computer password if requested. You may not see characters while
   typing the password; this is normal.
4. Your project is now in **Home → ai-php-web-apps**. Continue to Step 2.

Other Linux distributions require you to prepare Docker and Python yourself;
the automatic Linux installer targets Ubuntu and Debian.

## 2. Start the app

Open your project folder and use the launcher for your computer:

| Computer | Open this folder | Start with |
|---|---|---|
| Mac | **Start and Stop → macOS** | Double-click **Start.command** |
| Windows | **Start and Stop → Windows** inside the WSL folder | Double-click **Start.cmd** |
| Linux | **Start and Stop → Linux** | Run **Start.sh** in a terminal |

For the Linux download location above, you can start it by entering:

```sh
bash "$HOME/ai-php-web-apps/Start and Stop/Linux/Start.sh"
```

**What to expect:**

1. A terminal window opens or starts showing progress.
2. The launcher checks for the required software. Docker may install or open.
   Complete any computer-password, installation or Docker setup prompts.
3. You may see **Preparing your app. The first start may take a few minutes…**.
   Initial downloads and builds can take longer on a slow connection, and the
   terminal may remain quiet while they run. Leave it open until startup finishes.
4. The app prepares local storage and waits for its services to become ready.
5. Your browser opens. On a fresh Mac/Linux start, expect **Welcome to your app**
   and a **Set up admin account** button. Windows may open the admin setup form
   directly. Both are correct.

The browser address starts with `http://localhost:` followed by a number chosen
for this app. **Localhost means this computer.** Use the address the launcher opens;
do not assume a particular number or copy an address from someone else's setup.

If the browser does not open, run Start again. Once the app is ready, reopening
Start is safe and does not erase your work. Do not post the initial setup link or
setup code publicly: it grants access to claim the administrator account.

## 3. Create your administrator account

1. On the welcome screen, click **Set up admin account**. Skip this step if the
   **Set up your admin** form is already open.
2. The **Setup code** field should already be filled when you follow the link
   opened by Start. If it is blank, run Start again and use the newly opened page.
   Mac/Linux users can also copy the private setup code shown in their startup
   terminal into this field. Do not send the code to anyone.
3. Choose a **Username** with 3–64 characters: letters, numbers, dots, hyphens or
   underscores. Spaces are not accepted.
4. Choose a **Password** of 12–72 characters and save it in your password manager.
   This is your App Studio password, not your computer or AI-provider password.
5. Click **Create admin account** once and wait for the response.
6. You should arrive in **Your workspace**, with **Build with AI**, **Settings**,
   **Open your app** and **Sign out** in the navigation.

There is no shared default username or password. This stack has one administrator
account. If you see **Sign in** instead of setup, this copy has already been
configured: use its existing account. Do not reset it just to get past sign-in.

## 4. Protect your admin account

Open **Settings**. Each section has its own save or confirmation button; saving
one section does not save the others.

### Enable two-factor sign-in

An authenticator app creates a short code that changes regularly. With this
protection enabled, signing in requires your password and a code.

1. Find **Two-factor sign-in**.
2. Enter your **Current password**, then click **Set up authenticator**.
3. In your authenticator app, add an account using **Enter setup key** and select
   **time-based** codes. Copy the displayed **Setup key** into it. If your
   authenticator is on the same device, you can try **Open authenticator on this device**.
4. Enter your current admin password and the authenticator's **Six-digit code**
   into the website form. Click **Enable two-factor sign-in**.
5. Save the recovery codes immediately using **Download recovery codes**. Each code
   works once, and this set will not be displayed again. Keep them separately from
   your password, then click **I saved them**.
6. Check that the status reads **Authenticator enabled. Password and code are required.**

At future sign-ins, enter your username and password first, then the authenticator
code. An unused recovery code can replace the authenticator code if necessary.

### Save your backup recovery key

This key is different from both your password and your authenticator recovery codes.
It is needed to restore encrypted backups if the app's local copy of the key is lost.

1. In Settings, find **Encrypted backup recovery**.
2. Enter your current admin password and an authenticator code if two-factor sign-in
   is enabled.
3. Click **Download backup recovery key**.
4. Store the downloaded file somewhere safe, separate from the backups and preferably
   separate from this computer. Do not leave its only copy in Downloads.

**If you lose both copies of this key, encrypted backups cannot be restored.**
Anyone who has both a backup and its key can restore the information inside.
Downloading the key does not create a backup of your website. See the
[backup and recovery guide](application/docs/RECOVERY.md) for backup instructions.

## 5. Connect an AI assistant

Choose **one** of the following methods to get started. You do not need both.

### Option A: Use an API key

An API key lets this app send requests through your provider account. Provider API
usage may incur charges; review your account's billing and limits first.

1. In Settings, find **Connect your AI**.
2. Select **OpenAI (Codex / GPT models)** or **Anthropic (Claude)**.
3. Sign in to your provider's official developer console and create an API key.
   Copy it privately; never put it in a chat message, public issue or screenshot.
4. Return to this app and paste it into **API key**.
5. Check **Model ID** against a model available to your provider account. A starting
   value is filled in, but your account may need a different supported model.
6. Enter your **Current admin password** and click **Save provider**.
7. The provider status should show **key saved** and the model name. The key field
   clears after saving; this is expected. Your saved key is not displayed again.

Saving a key confirms it was stored; your first AI request checks whether that key
and model can actually be used. A provider chat subscription does not by itself
confirm API access. To change the model later without replacing the key, leave the
API key field blank and save again.

### Option B: Sign in with a Codex or Claude Code account

Here, **CLI** means the provider's command-line tool. The app manages it for you;
you use a private login popup instead of typing AI commands into a terminal.
Your provider account must support the selected connection, and its limits apply.

1. In Settings, find **Use your CLI account**.
2. Select **Codex CLI** or **Claude Code CLI**.
3. Enter your **Current admin password** and click **Open CLI login**.
4. A **CLI sign-in** popup opens with instructions and a provider login link.
5. Open that link and complete sign-in on the provider's official website. Enter
   your provider password there, not in the App Studio chat or login-code field.
6. Follow the popup instructions. If a **One-time authentication code** field
   appears, paste the code returned by the provider and click **Send login code**.
7. Keep the popup open until it reports **Connected**. Closing it before completion
   cancels the pending login. Then close it and confirm Settings shows **connected**.

This connection is administrator-only. If Settings says **CLI service unavailable**,
run Start and refresh the page before trying again.

## 6. Finish your dashboard preferences

### Optional: Show the floating chat button

1. In Settings, find **Chat while you build**.
2. Tick **Show floating chat button**.
3. Click **Save display setting**.
4. Click **Open your app** or **View app**. While you are signed in as admin, a
   **Build with AI** button lets you open the chat over your website.

The button is hidden from visitors and signed-out administrators, even when the
checkbox is enabled. Untick it and save to remove the shortcut.

### Optional: Change your username, password or admin address

1. In Settings, find **Keep your workspace yours**.
2. Change **Admin username** if wanted.
3. Keep **Admin address** as `/admin`, or enter a different supported path such as
   `/workspace`. This is the end of your local address, not a whole website URL.
4. Enter a **New password** only if you want to replace it. Leave it blank to keep
   your existing password.
5. Enter your **Current password**, then click **Save account changes**.

**Changing the admin address moves your sign-in page.** Save the new address and
update any bookmark; the old path no longer serves your dashboard. Account changes
sign out other sessions. Running Start again will open the current admin address.

**Your dashboard is ready when:** you can sign in, at least one AI connection is
saved or connected, and you have saved your recovery information. The assistant
should now appear in the **Build with AI → Assistant** selection menu.

## 7. Send your first building request

1. Open **Build with AI**.
2. Select your connection in **Assistant**.
3. Type a simple request, for example:

   > Create a simple black-and-white homepage for my small business.
   > Ask me what the business does before you propose changes.

4. Click **Send request** and wait. The request may move through queued and
   generating states. Avoid sending the same request repeatedly.
5. Read the reply. If it proposes changes, click **Review & apply** and read the
   explanation, affected files and warning. Ask the assistant to explain anything
   you do not understand before you approve it.
6. To proceed, tick **I understand this effect and want to continue**, enter your
   current admin password, and click **Back up and apply**. To decline the proposal,
   close the review and choose **Keep current website**.
7. Wait for the applied result, then click **View app** to inspect your website.

Your request and website source are sent to the selected AI provider. Do not paste
passwords or private customer records into chat. Automatic security checks catch
some problems, but do not guarantee safe code. The browser assistant edits your
website and its database schema; protected admin and platform files are outside
its editing scope.

**Undo can remove newer website data:** it restores the website database to the
backup from before that change. Read the warning before using **Undo this change**.

## 8. Stop the app safely

Closing a browser tab or signing out does **not** stop the app's background services.

1. Wait for any AI generation, apply, undo or backup operation to finish.
2. If you are leaving a shared computer, choose **Sign out** in the dashboard.
3. Open the same project folder you used to start the app.
4. Open the Stop launcher for your computer:

   | Computer | Stop with |
   |---|---|
   | Mac | **Start and Stop → macOS → Stop.command** |
   | Windows | **Start and Stop → Windows → Stop.cmd** in the WSL folder |
   | Linux | Run **Start and Stop → Linux → Stop.sh** in a terminal |

   For the Linux location used in this guide:

   ```sh
   bash "$HOME/ai-php-web-apps/Start and Stop/Linux/Stop.sh"
   ```

5. Wait until the terminal confirms the app is stopped. It keeps your website,
   account, saved keys and data on this computer.
6. When asked whether to quit Docker too, press **Enter** to leave Docker running.
   Type **yes** only if you also want Docker to stop.

**Quitting Docker can interrupt every other app using Docker on this computer.**
It does not delete their saved data. Wait for the shutdown result before closing
the terminal. If it says Docker is still responding, check Docker's window.

After stopping, the app's browser page will no longer load until you start it again.
Do not delete its folders to shut it down.

## 9. Come back later

1. Open **Start** from the same project folder. After a computer restart, do this
   before trying your browser bookmark.
2. If the app is already healthy, Start reopens its admin page without rebuilding it.
   If it was stopped, wait for startup to complete.
3. Sign in with your saved username and password, then your authenticator code if
   enabled. An existing browser session may open the dashboard directly.
4. Continue in **Build with AI**. Your settings, saved work and conversation remain.

You do not need to create another admin account or reconnect a saved provider at
every start. A provider may require you to reconnect if its authorization expires.

## If something does not work

| What you see | What to do |
|---|---|
| Python 3 is missing | Install Python 3; on Windows install it inside Ubuntu. Reopen Start. |
| Docker is not ready | Open Docker, finish its setup prompts, then run Start again. |
| Windows says the folder is unsupported | Open the project from its WSL Linux home folder, not `C:` or OneDrive. |
| Windows asks for WSL Integration | Enable your distribution in Docker Desktop settings, then reopen Start. |
| No browser window | Run Start again; use the address it opens. |
| Empty or invalid setup code | Reopen Start and use its setup page. Keep the code private. |
| Username/password rejected during setup | Follow the username rules and password length in Step 3. |
| Authenticator code rejected | Use a fresh code and check that your phone's clock is correct; if needed, use an unused recovery code. |
| “Add a provider in Settings” or disabled Send button | Complete Step 5 and return to the chat. |
| AI request fails after saving a key | Check the displayed error, account access, model ID and billing/usage limits. Saving a key does not test it. |
| Floating chat button missing | Sign in as admin, enable the setting, save it and refresh your app. |
| “Startup stopped safely” | Keep the message and ask your assistant for help. The private diagnostic log is `application/data/private/startup.log`; do not upload it publicly without checking for private information. |

If you have forgotten your administrator password and cannot sign in, ask for
help recovering the existing installation. **Do not use reset or delete data as a
password-recovery shortcut.**

## Keep your work safe

| Item | What it is for |
|---|---|
| **Start and Stop** | Your computer's start/stop launchers. |
| **application** | The app, your generated website and local saved information. Keep it in place. |
| **README.md** | This guide. |
| **AGENTS.md / CLAUDE.md** | Shared instructions for your AI assistants. |

Your custom website, account, API/CLI credentials, data and backups are excluded
from the public platform repository. **Pushing the platform to GitHub does not
back up your website.** Arrange private backups before important changes. Avoid
sharing the entire working project folder: it contains private files that Git ignores.

## Project information

- [Technical guide](application/docs/TECHNICAL-GUIDE.md): configuration, architecture and maintenance.
- [Backup recovery](application/docs/RECOVERY.md): private backups and restoration.
- [Verification](application/TEST-RESULTS.md): tested features and known limits.
- [Contributing](.github/CONTRIBUTING.md): development and safe test data.
- [Security](.github/SECURITY.md): reporting problems privately.

This repository does not currently include a license. A license must be selected
by the copyright owner before it is presented as an open-source release.
