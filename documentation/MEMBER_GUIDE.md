# Convota — Member & Delegate User Guide

Welcome to the **Convota Member & Delegate User Guide**. This guide provides practical, step-by-step instructions for organization delegates, representatives, and members participating in General Assembly meetings, managing your delegation roster, designating attending voters, and securely casting ballots.

---

## 1. Introduction: How Convota Works

**Convota** (*"Voting Together"*) is an assembly information system where:
* **The vote belongs to your organization:** Your entity (association, university, NGO, consortium partner, or union) holds the voting right.
* **The representative exercises that right:** You and your colleagues form your organization's **Delegation**. Any authenticated member of your delegation can access your organization's portal, coordinate who attends, and designate the active voter for each assembly session.
* **Intra-delegation transparency:** When an action is taken—such as logging in via a shared key or casting a ballot—all members of your delegation receive an immediate email notification so everyone stays informed.

---

## 2. Accessing the System & Authentication

Convota provides two complementary ways to authenticate:

### Method A: Individual Email & Password Login
1. **Initial Account Setup:** When your organization or the assembly administrator registers you, you receive a welcome email with a secure link:
   ```text
   https://<domain>/convota/auth/reset-password?token=...
   ```
2. Click the link to choose a strong password (minimum 8 characters).
3. Log in anytime at `https://<domain>/convota/login` using your registered email and password.
4. **Forgot Password:** If you ever misplace your password, click **Forgot Password?** on the login screen, enter your email address, and a reset link will be dispatched immediately.

### Method B: Shared Organization Access Key (Common Hash)
Every member organization is assigned a unique, 64-character alphanumeric **Shared Organization Access Key**. This key is shared among the members of your delegation.

* **One-Click Direct Login Link:**
  Your delegation can log into Convota directly without entering a username or password by opening the direct URL:
  ```text
  https://<domain>/convota/member_login.sh&hash=<your_organization_key>
  ```
* **Copying the URL:** Inside the **Organization Portal**, click **Copy URL** next to the Direct Delegation Access Link box to copy this link to your clipboard.
* **Security Alert Notifications:** Whenever anyone logs into the portal using the shared access key, Convota automatically emails all registered members of your delegation with a security notification containing the timestamp and client IP address.

---

## 3. Managing Your Organization Delegation Roster

Convota allows any authorized member of an organization to manage their delegation roster directly from the **Organization Portal** (`/convota/organizations/portal`).

### 3.1 Viewing Delegation Members
The portal displays all registered delegates with their roles:
* **Primary Delegate:** The primary point of contact for the Presidium.
* **Substitute Delegate(s):** Alternate representatives who can represent your organization if the primary delegate is absent.

### 3.2 Adding a New Delegation Member
1. In the Organization Portal, locate the **Add New Member** card.
2. Enter the new colleague's **First Name**, **Surname**, **Email Address**, and optional **Telephone Number**.
3. Click **Add Member to Delegation**.
4. The new member is added as a Substitute Delegate, and Convota automatically sends them a password setup email so they can log in independently.

### 3.3 Reorganizing Roles & Primary Delegate
* To designate a substitute member as the new Primary Delegate, click **Set as Primary**.
* The designated member becomes the Primary Delegate, and all other members are automatically adjusted as Substitutes.
* If a primary delegate leaves your organization, removing them automatically promotes the longest-standing substitute to primary delegate.

---

## 4. Participating in General Assembly Meetings

Before an assembly can proceed with voting, it must establish **Quorum** (the minimum required attendance of voting member organizations).

### 4.1 Meeting Notices & Presence Reminders
* When an assembly meeting is scheduled, you may receive an email notice with the meeting title, UTC time, and presence registration link.
* **Attendance Reminders:** If your delegation has not yet designated who is attending, the Assembly Presidium may send an urgent reminder email with direct links to register your presence.

### 4.2 Designating the Attending Representative (Who Is Online / Voting)
During meeting registration or while the meeting is in session:
1. Log in via your personal account or your delegation's direct access link.
2. Navigate to the **Organization Portal** (`/convota/organizations/portal`) or the **Meeting Presence** screen (`/convota/meetings/presence?id=X`).
3. Under **Assembly Meeting Presence**, find the dropdown labeled **Designated Attending Representative**.
4. Select the member of your delegation who is actively attending and authorized to vote (e.g., *Minas Dasygenis*).
5. Click **Check In / Save Attendance**.
6. Your organization is instantly marked as **✓ PRESENT** in the assembly hall roll-call and live quorum meter.

> **Note:** Only the designated attending representative can cast the organization's ballot during that meeting session. If your primary delegate must leave early, any delegation member can return to this dropdown, switch the designation to a substitute delegate who is online, and click *Save Attendance*.

---

## 5. Casting Ballots in Voting Sessions

When the Presidium opens voting on a resolution or proposal, voting delegates can cast their ballots.

### 5.1 Receiving the Voting Notification
When voting opens, all active voting delegates receive an email alert:
```text
Subject: [Voting Open] General Assembly Proposal: 'Approval of Annual Research Budget 2026'
```
The email contains a direct link to the ballot screen: `https://<domain>/convota/proposals/vote?id=X`.

### 5.2 Casting Your Vote
1. Open the proposal voting link.
2. Review the proposal title, description, quorum status, and required majority.
3. Select your organization's decision:
   * **For Parliamentary Resolutions:** Choose **YES**, **NO**, or **ABSTAIN**.
   * **For Multi-Candidate or Choice Proposals:** Select your desired option (e.g., *Selection A*, *Selection B*, or *Abstain*).
4. Click **Submit Official Ballot**.

### 5.3 Cryptographic Voter Receipt Token
Upon submitting your vote, Convota immediately generates and displays a unique 64-character SHA-256 **Cryptographic Receipt Hash**:
```text
Receipt Hash: 7b83f064c1209b55f6e80b2a927a4e69b22a07c91e1d09e7c35f2998a44b1c82
```
* **Why this matters:** In secret ballots, Convota strictly decouples your vote choice from your organization's identity in the database (`anonymous_ballots` contains **no** organization ID and **no** user ID). Your receipt hash is your cryptographic proof that your ballot was recorded into the tally without revealing *how* you voted to anyone.
* Click **Copy Receipt Hash** and save it for your records.

### 5.4 Intra-Organization Vote Confirmation Email
The moment a ballot is submitted for your organization, Convota automatically dispatches an immediate email notification to **all registered members of your delegation**:
```text
Subject: [Vote Confirmed] Your Organization Cast a Vote: 'Approval of Annual Research Budget 2026'
Representative: Minas Dasygenis
Timestamp: 2026-10-02 10:30:00 (UTC)
```
This ensures complete internal transparency within your delegation, preventing rogue voting or confusion.

---

## 6. Verifying Your Secret Ballot Independently

Convota includes an independent public verification tool that lets you verify that your ballot is part of the official tally:
1. Navigate to **Verify Receipt** (`/convota/proposals/verify-receipt`).
2. Paste your 64-character receipt hash into the input field.
3. Click **Verify Ballot Receipt**.
4. The system cryptographically queries the decoupled ballot vault and confirms:
   * Proposal title
   * Confirmation that the ballot is securely recorded in the official tally
   * Verification timestamp

---

## 7. Viewing Results & Projector Display

* **Results Sealed While Open:** While voting is in progress, tallies are sealed to protect against biased voting trends.
* **Results Unsealed Upon Closure:** Once the Presidium closes the vote, the certified tallies are unsealed and displayed with:
  * Percentage breakdown for each option
  * Total weighted votes cast
  * Quorum met confirmation
  * Majority passed/failed indicator
* **Assembly Hall Big-Screen Mode:** If participating in person or via video conference, the Presidium will display the projector view (`/convota/proposals/monitor?id=X`) displaying live turnout and finalized tallies.

---

## 8. Frequently Asked Questions (FAQ)

### Q: Can our organization change our designated voter while the meeting is in progress?
**Yes.** If your primary delegate needs to step away, any member of your delegation can log in, select the substitute member in the **Designated Attending Representative** dropdown, and click *Check In / Save Attendance*. That substitute delegate can then cast subsequent ballots.

### Q: What should we do if our Shared Access Key was accidentally exposed?
Log into the Organization Portal, locate the **Shared Organization Access Key** section, and click **Rotate Access Key**. Convota will revoke the compromised key, generate a brand new key, and immediately email the new key and direct link to all registered members of your delegation.

### Q: Why do observer organizations see the ballot but cannot submit votes?
Observer organizations (0 voting weight) participate in the assembly, register attendance, and observe proceedings, but parliamentary rules prevent non-voting members from submitting ballots or influencing quorum.

### Q: Can I participate and vote from a smartphone or tablet?
**Yes.** Convota's user interface is fully responsive and optimized for mobile screens, tablets, laptops, and large projector displays.
