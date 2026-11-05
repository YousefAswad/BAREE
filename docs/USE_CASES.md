# Use cases (✓ = covered in phase 1 code or tests)

## Super Admin (BAREE)
- SA-01 Create an institution, choose a plan, grant trial credits ✓ (phase 2)
- SA-02 Suspend an institution; its users are blocked immediately ✓ (middleware)
- SA-03 Create an agent: layer-1 prompt, prompt blocks, guided-form schema, routing, customization policy, credit cost ✓ (data model)
- SA-04 Version an agent, run the fixed test set, publish gradually, roll back ✓ (phase 2)
- SA-05 Maintain shared prompt blocks used by many agents ✓ (data model + context builder)
- SA-06 Connect providers, set failover order, rotate keys ✓ (registry, encrypted keys)
- SA-07 Edit competencies, weights, level thresholds and level requirements ✓ (engine is fully data-driven)
- SA-08 Build assessment templates from the question bank, by role ✓ (data model + seed)
- SA-09 Review aggregated user feedback and convert it to draft test cases ✓
- SA-10 Monitor usage and cost per provider, agent and institution ✓ (ai_runs metering)
- SA-11 Time-boxed support access to an institution, fully audited ✓ (phase 2)

## Institution admin
- IA-01 Invite users one by one or by CSV, map job titles to role templates ✓ (phase 2)
- IA-02 Build campuses, departments, segments ("فئات") ✓ (data model)
- IA-03 Clone role templates, adjust permissions within limits ✓ (RoleService + tests)
- IA-04 Assign department heads limited to their department ✓ (scoped grants + tests)
- IA-05 Launch an assessment campaign for a department or segment ✓ (data model)
- IA-06 See readiness by department without seeing individuals unless consented ✓ (consents table, permission split)
- IA-07 Enable/disable agents per department, set monthly caps, write institution rules (layer 2) ✓ (gateway checks + tests)
- IA-08 Bring their own provider key ✓ (credential lookup order)
- IA-09 Assign mandatory courses and missions with deadlines, reminders and compliance ✓
- IA-10 Publish targeted content to roles, levels or skill gaps ✓ (phase 5)

## Department head
- DH-01 View department readiness and gaps ✓ (permission)
- DH-02 Assign missions to the department only ✓ (permission scope)

## Employee
- U-01 First login, profile, take the readiness assessment with confidence ratings ✓ (flow + tests)
- U-02 Resume an unfinished assessment ✓
- U-03 See result, passport, and exactly what blocks the next level ✓ (blockers)
- U-04 Run an agent from a guided form; names in inputs never reach the provider ✓ (tests)
- U-05 Add personal instructions to an agent (if the agent allows it) ✓ (tests)
- U-06 Add task files and task notes (layer 5) ✓ (phase 3: scanned uploads in context)
- U-07 Work inside a project with its own instructions and memory ✓ (layer 3)
- U-08 Rate an output; edits become feedback for BAREE ✓ (phase 4)
- U-09 Control consents (manager visibility, leaderboard, feedback sharing) ✓ (consents table)

## System
- SYS-01 Fail over between providers on overload/outage ✓ (tests)
- SYS-02 Never double-charge credits on retries ✓ (tries=1, debit after success)
- SYS-03 Block AI calls when credits or caps are exhausted ✓ (tests)
- SYS-04 Refuse cross-tenant reads and writes ✓ (tests)
- SYS-05 Keep audit and ledger immutable ✓

## Added in phase 2
- IA-11 Upload a student roster so names never reach any AI provider ✓
- IA-12 Choose the privacy mode (aggregate / consent / department heads) ✓
- IA-13 Live audience count while building a campaign or segment ✓
- U-10 Accept an invitation, set a strong password, complete the profile, start the right assessment for the role ✓
- SYS-06 Platform area invisible to non-staff (404) ✓
- SYS-07 Expired support sessions closed automatically ✓

## Added in phase 3
- U-11 Find tools for my role and gaps; see "soon" and "human review" tools clearly ✓
- U-12 Generate a full lesson pack, watch it being written, regenerate one part ✓
- U-13 Upload reference files; infected or macro files are refused ✓
- U-14 Organise courses → units → lessons, share a course with a colleague as viewer or editor ✓
- U-15 Edit an output manually or "in one sentence", keep every version ✓
- U-16 Export to Word, PowerPoint or PDF in Arabic, with AI disclosure ✓
- U-17 Build a learning scenario with text, image, voice, video, 3D, quiz and activity scenes ✓
- U-18 Share a read-only link with a class, revoke it any time ✓
- SYS-08 Media that would depict a real student is blocked and never charged ✓
- SYS-09 Provider result downloads are restricted to allow-listed hosts (SSRF) ✓

## Added in phase 4
- U-19 Practise in the lab with a coach; objective checks can't be talked around ✓
- U-20 Earn XP and badges once per achievement; lab results feed the passport ✓
- U-21 Take a course with server-graded quizzes and a lab lesson; download a verifiable certificate ✓
- U-22 Ask about institution policies and get an answer with numbered sources, or an honest "not found" ✓
- U-23 Connect a personal AI client over MCP with a revocable 90-day token ✓
- IA-14 Publish policies (text or files) to the hub; see questions the hub could not answer ✓
- SA-12 Publish global guides, missions and courses; switch them on/off ✓
- PUB-01 Anyone can verify a certificate serial; tampered records show as invalid ✓

## Added in phase 5
- U-24 See a personal feed: targeted posts, what is due, one suggested tool ✓
- U-25 Choose which notifications arrive where (in-app, e-mail, WhatsApp) ✓
- U-26 Link WhatsApp with a one-time code, ask the knowledge hub there, send STOP to opt out ✓
- U-27 Use the whole platform in Arabic or English ✓
- IA-15 See adoption, outputs, estimated time saved, CPD hours and department usage (k-anonymous) ✓
- IA-16 Pay invoices online or by transfer, add seats, schedule plan changes ✓
- SA-13 Subscribe institutions, confirm bank transfers, see MRR and past-due accounts ✓
- SA-14 Publish global posts targeted by role and level ✓
- SYS-10 Payment webhooks are signature-verified and processed once ✓
- SYS-11 Unpaid institutions are suspended after the grace period and reactivated on payment ✓

## Added in phase 6
- U-28 See my full passport: how I grew since my first assessment, what blocks the next level, my evidence and badges ✓
- U-29 Decide whether my manager sees my level; help (or not) improve agents; download my data; delete my account ✓
- U-30 Give a tool my own standing instructions and reference files ✓
- SA-15 Version shared prompt blocks and see how many agents use each ✓
- SA-16 Write assessment questions with quality rules (one best answer, at least one weak option) ✓
- SA-17 Edit plans (price, limits, features) ✓
- SA-18 Author courses (modules, reading/video/quiz/lab lessons) and lab missions ✓
- SYS-12 The database itself refuses cross-institution reads and writes for the app role ✓ (CI)
- SYS-13 No secret or internal field reaches any page (automated scan) ✓
