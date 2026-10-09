# Run the commission demo in your browser

This guide takes you from a fresh download to a working demo in your web browser. It is written for Windows 11 and PowerShell. You do not need to know Laravel or Docker.

For how the project works inside, see [README.md](README.md).

## Contents

1. [What you need](#1-what-you-need)
2. [Install Docker Desktop (skip if it already works)](#2-install-docker-desktop-skip-if-it-already-works)
3. [Check that Docker is ready](#3-check-that-docker-is-ready)
4. [Get the project](#4-get-the-project)
5. [Open PowerShell in the project folder](#5-open-powershell-in-the-project-folder)
6. [Start the application](#6-start-the-application)
7. [Check that it is ready](#7-check-that-it-is-ready)
8. [Open the demo in your browser](#8-open-the-demo-in-your-browser)
9. [What happens automatically](#9-what-happens-automatically)
10. [Try one sample calculation](#10-try-one-sample-calculation)
11. [Run the tests (optional)](#11-run-the-tests-optional)
12. [Stop and start again](#12-stop-and-start-again)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. What you need

- **Docker Desktop**, installed and running.
- **A web browser**, such as Edge, Chrome or Firefox.
- **Internet access**, to download the project and for the first build. The first build downloads PHP, MySQL and other components.
- **Git is optional.** You can download the project as a ZIP file instead.

You do **not** need to install PHP, Composer, MySQL, Node.js or XAMPP. Everything runs inside Docker.

You do **not** need Ubuntu or an Ubuntu terminal. Docker Desktop may use WSL 2 (Windows Subsystem for Linux) in the background, but you run every command in this guide from normal Windows PowerShell.

## 2. Install Docker Desktop (skip if it already works)

If Docker Desktop is already installed and step 3 works, skip to [step 4](#4-get-the-project).

1. Read Docker's official Windows guide: [Install Docker Desktop on Windows](https://docs.docker.com/desktop/setup/install/windows-install/). It lists the current system requirements and download links. Check them first, because they change over time.
2. Download the installer from that page or from the [Docker Desktop download page](https://www.docker.com/products/docker-desktop/).
3. Double-click **Docker Desktop Installer.exe**.
   - The installer asks for an installation mode. The default, **per-user**, does not need administrator rights.
   - When asked, keep **Use WSL 2** selected. WSL 2 is Docker's default and recommended way to run Linux containers on Windows.
4. Follow the installer and select **Close** when it finishes.
5. If Windows or the installer asks you to restart, restart the computer.
   - Turning on WSL 2 for the first time needs administrator rights once. It may also ask for a restart.
6. Docker Desktop does not start by itself after installation. Open the **Start** menu, search for **Docker Desktop** and open it.
7. Accept the Docker Subscription Service Agreement. Docker Desktop does not run until you accept it.
8. Wait until Docker Desktop shows that the engine is running.

**If installation fails** because virtualization is turned off, or because you are not allowed to install software, ask your IT team for help. Do not change BIOS/UEFI settings unless you know what you are doing.

**Licence for company reviewers:** Docker Desktop is free for personal use, education, non-commercial open source and small businesses. Larger organisations need a paid subscription. If you are reviewing on a company computer, check with your IT team first. See [Docker Desktop license agreement](https://docs.docker.com/subscription/desktop-license/).

## 3. Check that Docker is ready

1. Open **PowerShell**: open the **Start** menu, type `PowerShell` and press **Enter**.
2. Run:

   ```powershell
   docker version
   ```

   The output must show **two** sections: `Client:` and `Server:`. The `Server:` section means the Docker engine is running.

   If you only see `Client:` followed by an error such as `cannot connect` or `The system cannot find the file specified`, the engine is not running yet. Open Docker Desktop, wait until the engine is running, and try again.

3. Run:

   ```powershell
   docker compose version
   ```

   It should print something like `Docker Compose version v2.38.2-desktop.1`. Any v2 version is fine.

## 4. Get the project

Choose **one** of these two options.

### Option A: download a ZIP file (no Git needed)

1. 1. Open [the project on GitHub](https://github.com/tnpatil75/fg-commission-engine).
2. Download the ZIP:
   - **GitHub:** select **Code**, then **Download ZIP**.
   - **GitLab:** select **Code**, then **zip** under "Download source code".
3. Open your **Downloads** folder in File Explorer, right-click the ZIP file and select **Extract All…**, then **Extract**.
4. Open the extracted folder. Find the folder that contains `compose.yaml` and `Dockerfile`. The ZIP sometimes creates one folder inside another with the same name; go into the inner one if you need to.

You cannot run the commands from inside the ZIP file itself. Extract it first.

### Option B: clone with Git

If Git is installed, run this in PowerShell:

```powershell
cd "$HOME\Downloads"
git clone https://github.com/tnpatil75/fg-commission-engine.git FG-Part-B
cd FG-Part-B
```

## 5. Open PowerShell in the project folder

All commands from here on must run **in the project folder**. This is the folder that contains `compose.yaml` and `Dockerfile`.

**The easy way:**

1. Open the project folder in File Explorer.
2. Click the address bar at the top of the window.
3. Type `powershell` and press **Enter**.

A PowerShell window opens in that folder.

**Or use `cd`** in any PowerShell window. Put the path in quotes, especially if it contains spaces. For example:

```powershell
cd "C:\Users\YourName\Downloads\FG-Part-B"
```

**Check that you are in the right place:**

```powershell
Test-Path .\compose.yaml, .\Dockerfile
```

It must print `True` twice. If you see `False`, you are in the wrong folder.

## 6. Start the application

```powershell
docker compose up --build -d
```

- `--build` builds the application from the project files.
- `-d` runs it in the background, so you get the PowerShell prompt back when it finishes.

The **first** start downloads and builds everything. It can take several minutes, depending on your internet speed and computer. Later starts are much faster.

## 7. Check that it is ready

```powershell
docker compose ps
```

You should see two services, `app` and `db`. Both must show **(healthy)** in the `STATUS` column, for example:

```text
NAME                  ...   SERVICE   ...   STATUS                    PORTS
fg-commission-app-1   ...   app       ...   Up 9 seconds (healthy)    127.0.0.1:8080->8080/tcp
fg-commission-db-1    ...   db        ...   Up 15 seconds (healthy)   3306/tcp, 33060/tcp
```

If one shows **(health: starting)**, wait about half a minute and run `docker compose ps` again. On the first start, the application waits for the database before it starts.

If a service is missing, keeps restarting or shows **(unhealthy)**, look at the recent logs:

```powershell
docker compose logs --tail=100 app db
```

Then see [Troubleshooting](#13-troubleshooting).

## 8. Open the demo in your browser

Open **<http://127.0.0.1:8080>**.

The main screens are:

| Screen | Address | What it does |
|---|---|---|
| Calculate | <http://127.0.0.1:8080/calculate> | Calculate a commission or referral amount |
| Rules | <http://127.0.0.1:8080/rules> | View, add, edit/update and deactivate rules |
| History | <http://127.0.0.1:8080/calculations> | Past calculations, their audit detail and manual decisions |

Keep in mind:

- Docker Desktop must stay running while you use the demo.
- The address works only on the computer that runs Docker. It is not reachable from other computers.
- Use `http://`, not `https://`.

## 9. What happens automatically

You do not need to install Composer packages, create a `.env` file, or run database migrations or seeders yourself. On start-up, Docker does all of this:

1. **Creates the databases.** On the very first start, MySQL creates the demo database and a separate test database.
2. **Prepares the database tables.** Every start runs any missing database migrations.
3. **Adds sample data once.** If the database is empty, it adds the sample segments, regions, companies, sale types and four illustrative rules. Later starts do not add them again, so your changes are kept.
4. **Creates the application key once.** It generates a random key on the first start, saves it, and reuses it on later starts.
5. **Keeps your data.** Calculations, rule changes and decisions are stored in Docker volumes. They survive normal stops, restarts and rebuilds.

All rates and companies in the sample data are invented. They are not FG's real commercial terms.

## 10. Try one sample calculation

1. Open **<http://127.0.0.1:8080/calculate>**.
2. Fill in the form:

   | Field | Value |
   |---|---|
   | Product segment | Building Envelope |
   | Sale destination region | GCC |
   | Lead-generating company | Partner |
   | Sale type | Glass only |
   | Eligible sale amount | `100000.00` |
   | Currency | AED |

3. Select **Calculate**.

With the **original sample rule**, the result shows:

- **Fixed rule applied**
- Rate **5.00%**
- Commission / referral amount **AED 5,000.00**
- Payer **FG**, payee **Partner** (FG pays Partner)

If someone has already edited that rule on the Rules screen, new calculations use the edited rule, so the result will differ. Earlier calculations keep the result they had.

**"No matching rule — manual review required" is a normal result.** It appears when no active rule exists for the combination you chose. It does not mean the setup failed. The four sample rules cover only four of the 60 possible combinations.

## 11. Run the tests (optional)

Reviewers do not need to run the tests. If you want to, keep the application running and run:

```powershell
docker compose exec app php artisan test
```

The tests use a **separate** test database (`commission_test`). Running them does not change the demo data you see in the browser.

## 12. Stop and start again

**Stop** the demo:

```powershell
docker compose down
```

**Start** it again later, from the same project folder:

```powershell
docker compose up -d
```

Both commands **keep** your saved calculations, rule changes, decisions and the application key.

If you restart Windows while the demo is running, open Docker Desktop afterwards. The demo starts again by itself, unless you stopped it with `docker compose down`.

### Optional: destructive reset

> **Warning: this deletes data.** A reset permanently deletes all saved calculations, rule changes, manual decisions and the application key. It then starts again with fresh sample data.
>
> **Do not use the reset as a first troubleshooting step.** Most problems are solved by the steps in [Troubleshooting](#13-troubleshooting).

Only if you really want to start from a clean state:

```powershell
docker compose down -v
docker compose up --build -d
```

## 13. Troubleshooting

### "docker : The term 'docker' is not recognized"

Docker Desktop is not installed, or PowerShell was opened before the installation finished.

- Close PowerShell and open a new window.
- If that does not help, restart Windows and open Docker Desktop.
- If it still fails, reinstall Docker Desktop (see [step 2](#2-install-docker-desktop-skip-if-it-already-works)).

### Docker cannot connect, or `docker version` shows no `Server:` section

The Docker engine is not running. Open Docker Desktop from the Start menu, wait until the engine is running, and try again.

### The browser cannot open the page

1. Run `docker compose ps`. Both `app` and `db` must show **(healthy)**.
2. If not, run `docker compose logs --tail=100 app db` and read the last lines for an error.
3. Check the address: `http://127.0.0.1:8080`, with `http` and the right port number.

### Port 8080 is already in use

`docker compose up` fails with a message such as `port is already allocated` or `address already in use`. Another program is already using port 8080. Start the demo on port 8081 instead:

```powershell
$env:APP_PORT = "8081"
docker compose up --build -d
```

Then open **<http://127.0.0.1:8081>**. The other addresses change the same way, for example <http://127.0.0.1:8081/calculate>.

The `$env:APP_PORT` setting lasts only for that PowerShell window. If you open a new window, set it again before running `docker compose up`. Otherwise the demo goes back to port 8080.

### Download or build fails

Messages about timeouts, TLS, certificates or `failed to resolve` usually mean a network problem.

- Check your internet connection and run `docker compose up --build -d` again. Running it again is safe.
- On a company network, a proxy or firewall may block Docker downloads. Ask your IT team. Docker Desktop's proxy settings are under **Settings → Resources → Proxies**.

### Docker Desktop reports a WSL or virtualization problem

Follow Docker's official guidance: [Troubleshoot topics for Docker Desktop](https://docs.docker.com/desktop/troubleshoot-and-support/troubleshoot/topics/) (see "Docker Desktop fails due to Virtualization not working") and Microsoft's [Install WSL](https://learn.microsoft.com/en-us/windows/wsl/install) page. On a company computer, ask your IT team.

Do **not** delete WSL distributions, and do not use Docker Desktop's **Clean up data** or **Reset to factory defaults**. These delete Docker data, including the demo's saved data.

### The dropdowns on the Calculate screen are empty

The sample data has not been loaded. Look at the start-up messages first:

```powershell
docker compose logs --tail=100 app
```

- `Empty database: seeding masters and illustrative rules.` means the sample data was added on this start. Reload the page.
- `Existing data found: sample data not re-seeded.` means the database already had data from an earlier start.
- `The database did not become available in time.` means the database was too slow. Run `docker compose up -d` again and check `docker compose ps`.

Do not run seeding commands by hand before reading the logs.
