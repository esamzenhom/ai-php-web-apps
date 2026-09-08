# Welcome to App Studio

Build your own website by chatting with AI. Start the app, create your private
administrator account, then describe what you want to build.

## What you can build

Create a PHP website through the browser using Claude or Codex, review each
proposed change, and approve it before it is applied. Your website runs separately
from the administration tools. The default interface is simple black and white.

The stack uses PHP, PostgreSQL, Caddy and Docker. It is designed for one private
administrator. AI can edit the generated website and its schema; platform and
administrator code stay outside the browser chat's editing scope.

## Get the project

Clone this repository with Git, then follow the steps below. Git preserves the
shared Claude/Codex skill links and launcher permissions. Choose a folder you will
keep: your website and saved data will live inside it.

## Start your app

Open **Start and Stop**, choose your computer, and open **Start**:

| Your computer | Folder | File to open |
|---|---|---|
| Mac | `Start and Stop → macOS` | **Start.command** — double-click |
| Windows | `Start and Stop → Windows` | **Start.cmd** — double-click |
| Linux | `Start and Stop → Linux` | **Start.sh** — run in a terminal |

The first start may take a few minutes. If Docker is missing, the launcher can
install it on supported computers. Follow any computer-password or Docker setup
prompts. Python 3 is also required.

Your browser opens the app. Create your admin username and password, then open
**Settings** to connect Claude or Codex. You can use an API key or sign in through
the available CLI option. Then open the building chat and describe your website.

**Already running?** Open Start again to return to your admin page.

**Windows setup:** this version uses WSL2. Keep the project in your Linux home
folder, accessible through File Explorer at `\\wsl.localhost\Ubuntu\home`.
Enable that Linux distribution in Docker Desktop’s WSL Integration settings.
A regular `C:` folder is not supported. See the [setup details](application/docs/TECHNICAL-GUIDE.md)
if you need help preparing your computer.

## Stop your app

Open **Stop** in the same folder you used to start.

Your website, account, saved keys and data stay on your computer. You will then
be asked whether to quit Docker too. **Quitting Docker can interrupt other apps.**
Press Enter to leave it running, or type **yes** to quit it.

## What is in this folder?

| Item | What it is for |
|---|---|
| **Start and Stop** | The buttons for starting and stopping your app. |
| **application** | Your app and its saved work. Leave this folder in place. |
| **README.md** | This guide. |
| **AGENTS.md / CLAUDE.md** | Instructions for your AI assistants. You do not need to edit them. |

Your custom website and private information are excluded from the public platform
repository. **Pushing the platform to GitHub does not back up your website.** Ask
your assistant to make a private backup before important changes.

Do not delete `application` or its contents to fix a problem. If something fails,
keep the message on screen and ask your assistant for help. Never share setup codes,
passwords, API keys or private backups publicly.

In **Admin → Settings**, enable **Two-factor sign-in** with your authenticator app
and save its recovery codes. Also download your **backup recovery key** and keep it
somewhere safe, separately from your backups. If you lose both the computer and
that key, encrypted backups cannot be restored.

AI changes are automatically checked for common security problems before you can
apply them. These checks help protect your app, but do not guarantee every change
is secure.

[Technical setup and maintenance guide](application/docs/TECHNICAL-GUIDE.md)

## Project information

- [Technical guide](application/docs/TECHNICAL-GUIDE.md): configuration, architecture and maintenance.
- [Backup recovery](application/docs/RECOVERY.md): saving your recovery key and restoring work.
- [Verification](application/TEST-RESULTS.md): tested features and known limits.
- [Contributing](.github/CONTRIBUTING.md): development and safe test data.
- [Security](.github/SECURITY.md): reporting problems privately.

This repository does not currently include a license. A license must be selected
by the copyright owner before it is presented as an open-source release.
