# FLTS Project Master Context / AI Agent Handoff

> Purpose: This file is the single handoff context for any AI agent joining the FLTS project. Read it before proposing changes, writing code, updating documents, designing experiments, or planning sprints.
> Initial snapshot date: 2026-09-25. Latest repository verification/decision update: 2026-10-02.

## 0. Agent Operating Rules

- Treat this file as a context index, not as permission to invent missing facts.
- Never fabricate experiment results, accuracy numbers, dataset sizes, supervisor decisions, production URLs, API providers/models, or technology choices that are still TBD.
- When two artifacts conflict, prefer the more recent and more specific artifact, but flag the conflict instead of silently rewriting history.
- Preserve Human-in-the-Loop: AI-generated learning content is always a draft until an authorized Lecturer approves/publishes it.
- All four team members must participate in coding, testing, integration, documentation, and sprint work. Do not assign anyone a documentation-only role.
- Current MVP scope is intentionally limited. Do not add PPT/PPTX, advanced OCR, handwriting recognition, agentic AI, local production LLM serving, or full adaptive learning unless the scope is formally changed.
- Research claims require evidence. Product functionality and research evaluation are separate tracks that share the same RAG pipeline.

## 1. Authoritative Source Artifacts

Current source set, in practical precedence order:

1. `C1SE32_FLTS_Sprint1_Capstone(2).xlsx` — current Sprint 1 execution plan (Estimate/Actual/Chart).
2. `C1SE32_UserStory_FTLS_ver1.1(2).docx` — User Story document; revision history includes v1.1 dated 2026-09-24 with corrected Product Backlog/User Story traceability.
3. `C1SE32-Project Plan_FLTS_ver1.0(3).docx` — Project Plan v1.0 dated 2026-09-15.
4. Proposal reviewed earlier in the project: `C1SE32-Proposal_FLTS_ver1.1(1).docx` / Proposal v1.0 content dated 2026-09-03.
5. Conversation-confirmed project decisions not yet guaranteed to be reflected in documents are explicitly marked **Chat-confirmed / pending document sync**.

## 2. Project Identity

- Team/Class: C1SE.32, Capstone Project 1, CMU-SE 450, International School, Duy Tan University.
- Product acronym: **FLTS**.
- Preferred current English title: **FLTS: A RAG-Based Learning Content Generation Platform for Flipped Learning**.
- Project period: **2026-09-03 to 2026-12-05**.
- Mentor shown in current project documents: **MSc Huy, Truong Dinh**.
- Project leader / Scrum Master: **Trương Công Triều Nguyên**.

## 3. Team

| Member | Role | Email | Phone |
|---|---|---|---|
| Trương Công Triều Nguyên | Scrum Master & Developer | shellingofficical@gmail.com | 0907857735 |
| Trà Văn Minh Khoa | Developer | minhkhoa131103@gmail.com | 0702665686 |
| Lê Thế Khánh Hưng | Developer | lethekhanhhung1808@gmail.com | 0855482883 |
| Đặng Trung Vương | Developer | Dangtrungvuong2020@gmail.com | 0349474291 |

Team role principles:

- Scrum Master: Trương Công Triều Nguyên — coordinates Scrum events, progress, blockers, process compliance; also a Developer.
- Developers: all members — analysis, design, coding, integration, testing, code review, quality.
- Testers: all members — functional, integration, regression, AI-quality testing, defect documentation/verification.
- Product Owner is represented by the instructor/representative process in the Project Plan; do not invent a named Product Owner unless officially assigned.

## 4. Problem and Product Vision

FLTS supports Flipped Learning by reducing repetitive lecturer work when transforming existing teaching materials into pre-class learning objects. Instead of asking an LLM to freely generate from its pretrained knowledge, FLTS uses document-grounded Retrieval-Augmented Generation (RAG).

Core product flow:

```text
Lecturer uploads teaching document
  -> document processing
  -> text extraction / cleaning
  -> chunking
  -> embedding
  -> vector storage
  -> retrieval (+ optional re-ranking/context optimization)
  -> RAG generation
  -> structured learning-object draft
  -> validation
  -> Lecturer preview/edit/regenerate
  -> Lecturer approve & publish
  -> Student accesses published content
  -> selected learning events
  -> basic learning analytics
```

Target Learning Objects:

- Quiz
- Flashcard
- Mindmap
- Interactive Slide

## 5. Actors and Access Model

- **Lecturer:** manages courses/documents, requests generation, previews/edits/regenerates, approves/publishes, views basic analytics.
- **Student:** accesses only authorized courses and **published** learning objects; can take quizzes and interact with flashcards/mindmaps/slides.
- **Administrator:** basic account/role/system-data management within MVP.
- **System:** performs document processing, RAG, validation, event tracking.
- **Researcher:** prepares dataset/ground truth, runs baselines/proposed methods, ablation, reproducibility logging.

Critical invariant: **Students must never see draft learning objects.**

## 6. Scope

### In scope

- Web application for Lecturer, Student, Administrator.
- Authentication and authorization / RBAC.
- Course management and course access control.
- Teaching document upload and management.
- Input formats: **PDF, DOC, DOCX**.
- Text extraction, cleaning, chunking, embedding, vector storage, similarity retrieval.
- Re-ranking/context optimization is included as a Sprint 2 backlog item but is medium priority; implementation may be evaluated pragmatically.
- RAG-based generation of Quiz, Flashcard, Mindmap, Interactive Slide.
- Structured output validation.
- Lecturer review/edit/regenerate/approve/publish workflow.
- Student learning workspace.
- Basic learning-event tracking and basic analytics.
- Research evaluation package.

### Out of current MVP scope

- PPT/PPTX ingestion.
- Advanced OCR/scanned-PDF pipeline.
- Handwriting recognition.
- Advanced multimodal understanding of complex images/tables/formulas.
- Training a foundation model from scratch.
- Production-scale local LLM/vLLM serving.
- Agentic AI / multi-agent systems.
- Knowledge Graph RAG.
- Full adaptive/personalized learning diagnosis.

## 7. Architecture and Technology Direction

| Layer | Current direction |
|---|---|
| Frontend | Vue 3 + Vite |
| Application Backend | Laravel / PHP |
| AI Service | Python / FastAPI |
| Relational DB | MySQL |
| Vector Storage | ChromaDB local for the Sprint 2 vertical slice; image/client versions must be pinned before implementation |
| External AI | Google Gemini API: `text-embedding-004` (768 dimensions) and `gemini-1.5-flash` |
| DevOps | Docker / Docker Compose, Git/GitHub, CI/CD foundation |
| Tools | Trello, Zalo, Google Drive, Postman, MySQL Workbench, VS Code |

Architectural responsibility:

- Vue handles user interfaces.
- Laravel handles authentication/authorization, course/document business logic, learning-object workflow, APIs, relational data.
- FastAPI handles document processing, embeddings, retrieval, RAG, generation.
- MySQL stores users, roles, courses, document metadata, learning objects, learning events, and related business data.
- Vector storage stores embeddings + metadata for semantic retrieval.
- External cloud AI services provide supported embedding and LLM operations.

## 8. Research Track

The research track evaluates the document-grounded RAG pipeline, not merely the UI.

Required research package:

- Dataset
- Ground Truth
- Baseline configuration(s)
- Proposed Method
- Retrieval metrics
- Generation metrics
- Experiment logs
- Evaluation results
- Ablation study
- Reproducibility / rerun configuration
- Error analysis / discussion should be added when reporting results

### Dataset decision

**Chat-confirmed / pending document sync:** the intended data source is **DTU textbooks / teaching materials that the team is authorized to use**.

Important distinction:

- DTU textbooks/teaching files = raw data source / document corpus.
- A research evaluation dataset additionally needs documented selection criteria, evaluation questions/queries, ground-truth relevant passages, and reference answers where applicable.
- Do not invent the number of textbooks, questions, samples, annotators, or train/test split until the research design formally defines them.

### Research acceptance criteria from current User Stories

- Dataset sources and selection criteria are documented.
- Ground truth is defined.
- Data is validated and versioned.
- Baseline and proposed method use the same main evaluation conditions.
- Retrieval and generation metrics are calculated.
- Experiment results are stored.
- Ablation configuration is defined.
- Experiment logs store inputs, configuration, and results.
- Experiments can be rerun from recorded configuration.

### Research pipeline mental model

```text
Authorized DTU teaching materials
  -> parse/extract
  -> clean/normalize
  -> corpus
  -> chunk
  -> embed
  -> vector store
  -> evaluation queries
  -> ground truth relevant passages / reference answers
  -> baseline configurations
  -> proposed method
  -> controlled experiments
  -> retrieval metrics + generation metrics
  -> ablation
  -> reproducibility logs
  -> error analysis
  -> research conclusion
```

Research decisions still TBD unless separately approved:

- Exact DTU document set and usage authorization.
- Dataset size and sampling procedure.
- Ground-truth annotation/verification procedure.
- Exact baselines (minimum requirement exists, exact configurations need formalization).
- Exact proposed method/contribution.
- Exact metrics and thresholds.
- Exact chunk sizes/overlap/top-k/reranking configuration.

## 9. Project Deliverables

| Deliverable | Description |
|---|---|
| FLTS Web Application | Interface and functionality for Lecturers, Students, and Administrators within the MVP scope. |
| AI/RAG Service | Document processing, retrieval, and Learning Object generation services implemented via pipeline. |
| Database system | MySQL-based storage for accounts, courses, documents, Learning Objects, learning events, and related data. |
| Research Evaluation Package | Dataset, Ground Truth, Baseline, Proposed Method, metrics, experiment logs, and evaluation results. |
| Project documents | Project Plan, Requirements Engineering, Architecture, Test Plan/Test Cases/Test Report, and Technical Report as required by the course. |

Additional final delivery expectations include source repository, deployed/demo web app, system specification/architecture, test plan/cases/report, research evaluation package, and technical report.

## 10. Schedule, Capacity, and Milestones

- Planned total effort: **860 person-hours**.
- Team: 4 members.
- Planned individual total: **215 hours/person**.
- Course planning basis: **16 hours/person/week**.
- Team capacity: **64 person-hours/week**.
- Each 2-week development Sprint: **128 person-hours**.

| Milestone | Start | Finish | Main deliverables |
|---|---|---|---|
| Start-up & Requirements | 03/09/2026 | 24/09/2026 | Project Plan, Requirements, Use Cases, Architecture, DB/API, UI/Test Strategy |
| Sprint 1 – Foundation | 25/09/2026 | 08/10/2026 | Authentication, RBAC, Course Management, Document Upload Foundation |
| Midterm report | 27/10/2026 | 30/10/2026 | Midterm Report and Proof of Progress |
| Sprint 2 – RAG Vertical Slice | 09/10/2026 | 22/10/2026 | Document Processing, Chunking, Embedding, Retrieval, RAG Prototype |
| Sprint 3 – MVP Learning Workflow | 23/10/2026 | 05/11/2026 | Quiz, Flashcard, Lecturer Review/Publish, Student Workspace |
| Sprint 4 – Completion & Research | 06/11/2026 | 19/11/2026 | Mindmap, Interactive Slide, Analytics, Dataset, Ground Truth, Baseline/Proposed Evaluation |
| QA & Stabilization | 20/11/2026 | 29/11/2026 | System Test, AI Quality Test, Regression, Bug Fixing, Deployment Verification |
| Final Release | 30/11/2026 | 05/12/2026 | Final Validation, Research Results, Documentation, Demo, Submission |

Midterm report window: **2026-10-27 to 2026-10-30**.

## 11. Scrum Process

Each Sprint uses:

1. Sprint Planning
2. Sprint Execution
3. Daily Scrum
4. Sprint Review
5. Sprint Retrospective

Sprint work includes design, coding, integration, testing, code review, and bug fixing. Research is not postponed entirely to Sprint 4; the Project Plan explicitly says research activities are distributed from Sprint 2 through Final Release.

Communication:

- Daily team progress/blocker communication.
- Weekly mentor progress review.
- Sprint Planning/Review/Retrospective once per Sprint.
- Bi-weekly status report.

## 12. Product Backlog / User Story Traceability

The v1.1 User Story document corrected Product Backlog-to-User Story traceability. Use this mapping rather than older mappings.

| PB | US | Feature | Actor | Priority | Sprint | Estimate |
|---|---|---|---|---|---|---|
| PB01 | US-01 | Repository & Branching | Developer | High | Sprint 1 | 8h |
| PB02 | US-02 | Development Environment | Developer | High | Sprint 1 | 10h |
| PB03 | US-03 | Coding & Quality Standards | Developer | Medium | Sprint 1 | 6h |
| PB04 | US-04 | CI & Test Foundation | Developer | Medium | Sprint 1 | 8h |
| PB05 | US-05 | Registration | Lecturer / Student / Administrator | High | Sprint 1 | 12h |
| PB06 | US-06 | Login & Logout | User | High | Sprint 1 | 12h |
| PB07 | US-07 | Password Recovery | User | Medium | Sprint 1 | 8h |
| PB08 | US-08 | Role-Based Access Control | Administrator | High | Sprint 1 | 12h |
| PB09 | US-09 | Account & Role Management | Administrator | Medium | Sprint 1 | 10h |
| PB10 | US-10 | Course Management | Lecturer | High | Sprint 1 | 14h |
| PB11 | US-11 | Course Access Control | Lecturer / Student | High | Sprint 1 | 10h |
| PB12 | US-12 | Document Upload | Lecturer | High | Sprint 1 | 12h |
| PB13 | US-13 | Document Metadata & Status | Lecturer | High | Sprint 1 | 8h |
| PB14 | US-14 | Document Management | Lecturer | Medium | Sprint 1 | 8h |
| PB15 | US-30 | PDF Text Extraction | Lecturer | High | Sprint 2 | 16h |
| PB16 | US-31 | DOC/DOCX Text Extraction | Lecturer | High | Sprint 2 | 12h |
| PB17 | US-32 | Text Cleaning | Lecturer | Medium | Sprint 2 | 8h |
| PB18 | US-33 | Chunking | Lecturer | High | Sprint 2 | 12h |
| PB19 | US-34 | Embedding Generation | System | High | Sprint 2 | 14h |
| PB20 | US-35 | Vector Storage | System | High | Sprint 2 | 12h |
| PB21 | US-36 | Similarity Retrieval | System | High | Sprint 2 | 18h |
| PB22 | US-37 | Re-ranking & Context Optimization | System | Medium | Sprint 2 | 12h |
| PB23 | US-38 | RAG Generation Pipeline | System | High | Sprint 2 | 18h |
| PB24 | US-15 | Quiz Generation | Lecturer | High | Sprint 3 | 22h |
| PB25 | US-16 | Flashcard Generation | Lecturer | High | Sprint 3 | 18h |
| PB26 | US-17 | Mindmap Generation | Lecturer | High | Sprint 4 | 20h |
| PB27 | US-18 | Interactive Slide Generation | Lecturer | High | Sprint 4 | 22h |
| PB28 | US-19 | Structured Output Validation | System | High | Sprint 3 | 10h |
| PB29 | US-20 | Generation Parameters | Lecturer | Medium | Sprint 3 | 8h |
| PB30 | US-21 | Learning Object Preview | Lecturer | High | Sprint 3 | 12h |
| PB31 | US-22 | Learning Object Edit | Lecturer | High | Sprint 3 | 12h |
| PB32 | US-23 | Learning Object Regeneration | Lecturer | High | Sprint 3 | 10h |
| PB33 | US-24 | Approve & Publish | Lecturer | High | Sprint 3 | 14h |
| PB34 | US-25 | Learning Object Version & Status | Lecturer | Medium | Sprint 3 | 8h |
| PB35 | US-27 | Student Course Workspace | Student | High | Sprint 3 | 10h |
| PB36 | US-28 | Quiz Interaction | Student | High | Sprint 3 | 16h |
| PB37 | US-29 | Flashcard, Mindmap & Slide Interaction | Student | High | Sprint 4 | 12h |
| PB38 | US-39 | Learning Event Tracking | System | Medium | Sprint 4 | 10h |
| PB39 | US-26 | Basic Learning Analytics | Lecturer | Medium | Sprint 4 | 14h |
| PB40 | US-40 | Dataset & Ground Truth | Researcher | High | Sprint 4 | 12h |
| PB41 | US-41 | Baseline & Proposed Evaluation | Researcher | High | Sprint 4 | 16h |
| PB42 | US-42 | Ablation & Reproducibility | Researcher | High | Sprint 4 | 6h |

## 13. User Stories and Acceptance Criteria

### US-01 — Repository & Branching

**Story:** As a Developer I want to set up the repository, branches, and working conventions so that the team has a consistent source-code foundation and can collaborate effectively.
**Priority:** High  
**Actor:** Developer

Acceptance criteria:
- A main repository is available.
- The branch strategy is agreed.
- All team members have repository access.
- Commit rules are documented.

### US-02 — Development Environment

**Story:** As a Developer I want to set up a Docker-based development environment so that all team members can run the system services consistently.
**Priority:** High  
**Actor:** Developer

Acceptance criteria:
- Docker Compose can start the required services.
- Environment variables are configured.
- Setup instructions are verified.

### US-03 — Coding & Quality Standards

**Story:** As a Developer I want to apply coding conventions and a code-review workflow so that the source code is consistent and uncontrolled changes are reduced.
**Priority:** Medium  
**Actor:** Developer

Acceptance criteria:
- Coding conventions are defined.
- Pull requests are reviewed.
- Merge rules are agreed.

### US-04 — CI & Test Foundation

**Story:** As a Developer I want to establish the test structure and basic integration testing so that the team can detect defects early during development.
**Priority:** Medium  
**Actor:** Developer

Acceptance criteria:
- A test structure is available.
- Basic tests can run.
- The integration-test foundation is established.

### US-05 — Registration

**Story:** As a User I want to register an account so that I have an account that allows me to access the system.
**Priority:** High  
**Actor:** Lecturer / Student / Administrator

Acceptance criteria:
- Valid email/username is accepted.
- The password meets the defined policy.
- A valid account is stored successfully.
- Invalid registration data is rejected.

### US-06 — Login & Logout

**Story:** As a User I want to log in and log out so that I can access the system securely and end my session when needed.
**Priority:** High  
**Actor:** User

Acceptance criteria:
- Valid credentials allow login.
- Invalid credentials are rejected.
- Logout invalidates the active session/token.

### US-07 — Password Recovery

**Story:** As a User I want to recover my password when I forget it so that I can regain access to my account.
**Priority:** Medium  
**Actor:** User

Acceptance criteria:
- A password-recovery request can be created.
- The recovery token/link has an expiration.
- A new password can be set.
- A recovery token cannot be reused.

### US-08 — Role-Based Access Control

**Story:** As an Administrator I want to manage access permissions by role so that each type of user can access only the functions they are authorized to use.
**Priority:** High  
**Actor:** Administrator

Acceptance criteria:
- Lecturer, Student, and Administrator roles are available.
- Authorization middleware checks access rights.
- Unauthorized access is rejected.

### US-09 — Account & Role Management

**Story:** As an Administrator I want to manage user accounts and roles at a basic level so that I can maintain user information in the system.
**Priority:** Medium  
**Actor:** Administrator

Acceptance criteria:
- The Administrator can view the account list.
- Authorized role/status updates are supported.
- Management operations are authorization-checked.

### US-10 — Course Management

**Story:** As a Lecturer I want to create, view, update, and manage courses so that I can organize teaching materials by course.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- The Lecturer can create, edit, and view courses.
- Valid course names are accepted.
- Only the authorized Lecturer can manage the course.

### US-11 — Course Access Control

**Story:** As a Lecturer / Student I want to control access to courses so that only authorized users can access course content.
**Priority:** High  
**Actor:** Lecturer / Student

Acceptance criteria:
- The Lecturer can access their own courses.
- Students can access only permitted courses.
- Unauthorized course access is rejected.

### US-12 — Document Upload

**Story:** As a Lecturer I want to upload PDF, DOC, and DOCX teaching documents to a course so that the system has source documents that can be processed.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Only PDF, DOC, and DOCX files are accepted.
- Valid files are stored.
- Invalid files are rejected.
- The document is associated with the correct course.

### US-13 — Document Metadata & Status

**Story:** As a Lecturer I want to view document metadata and processing status so that I know the current state of each document.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- File name, file type, time, and processing status are displayed.
- Status changes according to the processing pipeline.
- Processing errors are clearly reported.

### US-14 — Document Management

**Story:** As a Lecturer I want to view and manage uploaded documents so that I can maintain the document list of my course.
**Priority:** Medium  
**Actor:** Lecturer

Acceptance criteria:
- The document list is displayed correctly.
- Document metadata can be viewed.
- Management actions apply only to authorized documents.

### US-15 — Quiz Generation

**Story:** As a Lecturer I want to generate a Quiz from teaching documents so that I have a quiz for learning activities.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- The Quiz contains questions, options, and answers.
- Quiz content is grounded in the source document.
- The Quiz schema is valid.
- Supported generation parameters are controlled.

### US-16 — Flashcard Generation

**Story:** As a Lecturer I want to generate Flashcards from teaching documents so that I have content for quick review.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Flashcards contain front/back content.
- Flashcard content is grounded in the source document.
- The Flashcard schema is valid.
- Flashcards can be previewed.

### US-17 — Mindmap Generation

**Story:** As a Lecturer I want to generate a Mindmap from teaching documents so that I have a visual structure for summarizing knowledge.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- The Mindmap has a root/node hierarchy.
- Mindmap content is grounded in the source document.
- The Mindmap schema is valid.
- The Mindmap can be rendered.

### US-18 — Interactive Slide Generation

**Story:** As a Lecturer I want to generate Interactive Slides from teaching documents so that I have interactive presentation content for learners.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Slides have a defined structure.
- Slide content is grounded in the source document.
- Navigation/interaction metadata is available.
- The Slide schema is valid.
- Slides can be rendered.

### US-19 — Structured Output Validation

**Story:** As the System I want to validate and normalize AI-generated JSON output so that learning objects do not contain invalid structures when stored or displayed.
**Priority:** High  
**Actor:** System

Acceptance criteria:
- JSON is validated against the required schema.
- Invalid output is detected.
- Invalid output cannot be published.

### US-20 — Generation Parameters

**Story:** As a Lecturer I want to select supported generation parameters so that I can adjust the generated result within the supported system constraints.
**Priority:** Medium  
**Actor:** Lecturer

Acceptance criteria:
- Only supported parameters are accepted.
- Out-of-range values are rejected.
- Generation parameters are stored with the request.

### US-21 — Learning Object Preview

**Story:** As a Lecturer I want to preview an AI-generated learning object so that I can review the content before publication.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- The preview displays the correct learning-object type.
- The preview uses draft data.
- Rendering errors are reported.

### US-22 — Learning Object Edit

**Story:** As a Lecturer I want to edit a learning object so that I can correct AI-generated content before publication.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Allowed fields can be edited.
- Valid changes are stored.
- Changes are reflected in the preview.

### US-23 — Learning Object Regeneration

**Story:** As a Lecturer I want to regenerate a learning object so that I can improve content that does not meet my requirements.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Regeneration can use the same source.
- The previous draft is retained according to version/history handling.
- The new output is validated.

### US-24 — Approve & Publish

**Story:** As a Lecturer I want to approve and publish a learning object so that students can access only reviewed and approved content.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Only the authorized Lecturer can approve/publish.
- The publication status changes correctly.
- Students cannot access drafts.

### US-25 — Learning Object Version & Status

**Story:** As a Lecturer I want to track learning-object versions and statuses so that I know whether content is a draft, approved, or published and can identify the current version.
**Priority:** Medium  
**Actor:** Lecturer

Acceptance criteria:
- Learning-object statuses are clearly defined.
- Important changes are recorded.
- The current version can be identified.

### US-26 — Basic Learning Analytics

**Story:** As a Lecturer I want to view basic learning statistics so that I can monitor learning-object usage in my courses.
**Priority:** Medium  
**Actor:** Lecturer

Acceptance criteria:
- Basic metrics are displayed from learning-event data.
- Data can be filtered by course or learning object.
- The feature remains basic and does not require adaptive diagnosis.

### US-27 — Student Course Workspace

**Story:** As a Student I want to access my courses and published learning objects so that I have a centralized learning workspace.
**Priority:** High  
**Actor:** Student

Acceptance criteria:
- Students see only permitted courses.
- Only published learning objects are displayed.

### US-28 — Quiz Interaction

**Story:** As a Student I want to take a Quiz and receive the result so that I can check my knowledge.
**Priority:** High  
**Actor:** Student

Acceptance criteria:
- Students can select answers and submit a Quiz.
- The score is calculated.
- The Quiz attempt is stored.
- Invalid interaction data is handled.

### US-29 — Flashcard, Mindmap & Slide Interaction

**Story:** As a Student I want to use Flashcards, Mindmaps, and Interactive Slides so that I can learn using the published learning-object types.
**Priority:** High  
**Actor:** Student

Acceptance criteria:
- The correct published object can be opened.
- Navigation, flip, or interaction works according to the object type.
- Students cannot edit the published content.

### US-30 — PDF Text Extraction

**Story:** As the System I want to extract text from PDF documents so that PDF content can be passed to the RAG pipeline.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Valid PDF files can be read.
- Text is extracted from the PDF.
- File errors are handled.
- The extracted result can be stored for the next step.

### US-31 — DOC/DOCX Text Extraction

**Story:** As the System I want to extract text from DOC and DOCX documents so that Word content can be passed to the RAG pipeline.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Valid DOC and DOCX files can be read.
- Text is extracted from the document.
- Formatting errors are handled.

### US-32 — Text Cleaning

**Story:** As the System I want to clean and normalize extracted text so that noise is reduced before chunking and embedding.
**Priority:** Medium  
**Actor:** Lecturer

Acceptance criteria:
- Basic noise is removed.
- Whitespace and characters are normalized.
- Meaningful content is retained.

### US-33 — Chunking

**Story:** As the System I want to split documents into chunks with metadata so that retrieval can identify relevant parts of the source document.
**Priority:** High  
**Actor:** Lecturer

Acceptance criteria:
- Chunks have an appropriate size.
- Document metadata is included.
- Important content is not lost.
- Chunk configuration is stored.

### US-34 — Embedding Generation

**Story:** As the System I want to generate embeddings for document chunks so that the chunks have vector representations for semantic search.
**Priority:** High  
**Actor:** System

Acceptance criteria:
- Each valid chunk has an embedding.
- Embedding API errors are handled.
- Chunk metadata linkage is preserved.

### US-35 — Vector Storage

**Story:** As the System I want to store embeddings and metadata in the vector store so that the system can retrieve chunks using vector similarity.
**Priority:** High  
**Actor:** System

Acceptance criteria:
- Vectors and metadata are stored.
- Stored vectors can be queried.
- Vector data is linked to the source document.

### US-36 — Similarity Retrieval

**Story:** As the System I want to retrieve the top-k chunks relevant to a query so that the RAG pipeline receives relevant context.
**Priority:** High  
**Actor:** System

Acceptance criteria:
- The query is embedded.
- Top-k results are returned.
- Results include scores and metadata.

### US-37 — Re-ranking & Context Optimization

**Story:** As the System I want to optimize and re-rank retrieved context so that the LLM receives more relevant context with less unnecessary information.
**Priority:** Medium  
**Actor:** System

Acceptance criteria:
- A context selection/re-ranking mechanism is available.
- The context limit is controlled.
- Results can be compared with basic retrieval.

### US-38 — RAG Generation Pipeline

**Story:** As the System I want to generate learning objects using context retrieved from teaching documents so that generated content is grounded in the source documents.
**Priority:** High  
**Actor:** System

Acceptance criteria:
- The prompt receives retrieved context.
- The LLM returns the required structure.
- The output is validated.
- Generation errors are handled.

### US-39 — Learning Event Tracking

**Story:** As the System I want to record basic learning events so that the system has data for basic learning analytics.
**Priority:** Medium  
**Actor:** System

Acceptance criteria:
- Selected events such as opening content, completion, and Quiz attempts are recorded.
- Events are linked to the correct user, object, and time.

### US-40 — Dataset & Ground Truth

**Story:** As a Researcher I want to prepare the dataset and ground truth for evaluation so that the evaluation pipeline has test data that can be assessed.
**Priority:** High  
**Actor:** Researcher

Acceptance criteria:
- The dataset has documented sources and selection criteria.
- Ground truth is defined.
- The data is validated and versioned.

### US-41 — Baseline & Proposed Evaluation

**Story:** As a Researcher I want to run the baseline and proposed methods under comparable conditions so that I can objectively compare the evaluated methods.
**Priority:** High  
**Actor:** Researcher

Acceptance criteria:
- The baseline and proposed method use the same main evaluation conditions.
- Retrieval and generation metrics are calculated.
- Experiment results are stored.

### US-42 — Ablation & Reproducibility

**Story:** As a Researcher I want to run ablation experiments and keep experiment logs so that I can identify component contributions and reproduce experiments.
**Priority:** High  
**Actor:** Researcher

Acceptance criteria:
- An ablation configuration is defined.
- Experiment logs store inputs, configuration, and results.
- Experiments can be rerun from the recorded configuration.

## 14. Sprint 1 — Foundation (Current Execution Plan)

- Sprint dates: **2026-09-25 to 2026-10-08**.
- Sprint scope: PB01–PB14 plus Sprint ceremonies/integration/testing.
- Total planned Sprint 1 scope: **128 hours**.

Per-member planned effort:

| Member | Planned Sprint 1 hours |
|---|---:|
| Truong Cong Trieu Nguyen | 32 |
| Tra Van Minh Khoa | 32 |
| Le The Khanh Hung | 32 |
| Dang Trung Vuong | 32 |

Current status from workbook:

- The `Actual` sheet contains no task actual-hours entries yet. Therefore no completion percentage should be inferred from the file.
- The `Chart` sheet contains planned burndown/completion series; actual series are currently blank in the uploaded workbook.

### Sprint 1 task breakdown

| PB | Task | Owner | Estimate (h) |
|---|---|---|---:|
| SPR1 | Sprint Planning Meeting | All Members | 4 |
| SPR1 | Create Sprint 1 Backlog | All Members | 2 |
| SPR1 | Create Sprint 1 Test Plan | Vuong | 2 |
| PB01 | Initialize Git repository and project structure | Nguyen | 2 |
| PB01 | Define Git branching strategy | Nguyen | 2 |
| PB01 | Configure branch protection and pull request rules | Nguyen | 2 |
| PB02 | Create Docker Compose services | Khoa | 3 |
| PB02 | Configure Laravel backend environment | Khoa | 2 |
| PB02 | Configure Vue frontend environment | Hung | 2 |
| PB02 | Verify local development environments | Nguyen | 1 |
| PB03 | Define coding conventions | Nguyen | 2 |
| PB03 | Create code review checklist | Nguyen | 2 |
| PB04 | Configure CI pipeline | Khoa | 3 |
| PB04 | Create unit-test foundation | Vuong | 2 |
| PB04 | Configure linting and quality checks | Nguyen | 1 |
| PB05 | Implement Registration API | Khoa | 4 |
| PB05 | Build Registration UI | Hung | 3 |
| PB05 | Validate registration inputs and errors | Vuong | 3 |
| PB06 | Implement Login API | Khoa | 4 |
| PB06 | Build Login UI | Hung | 3 |
| PB06 | Implement logout and session handling | Hung | 3 |
| PB07 | Implement password-reset token and API | Khoa | 3 |
| PB07 | Build password recovery UI and email flow | Hung | 2 |
| PB07 | Test password recovery | Vuong | 1 |
| PB08 | Create role and permission schema | Khoa | 3 |
| PB08 | Implement RBAC middleware | Khoa | 4 |
| PB08 | Test authorization rules | Vuong | 3 |
| PB09 | Implement account list and detail API | Vuong | 3 |
| PB09 | Implement role assignment and update | Nguyen | 3 |
| PB09 | Test account and role management | Vuong | 2 |
| PB10 | Implement Course CRUD API | Hung | 5 |
| PB10 | Build Lecturer course management UI | Hung | 4 |
| PB10 | Test course management integration | Hung | 3 |
| PB11 | Create course access and enrollment model | Khoa | 3 |
| PB11 | Implement course access middleware | Vuong | 3 |
| PB11 | Test Lecturer and Student access | Vuong | 2 |
| PB12 | Implement document upload API | Vuong | 4 |
| PB12 | Build document upload UI | Hung | 3 |
| PB12 | Validate file type, size, and upload errors | Vuong | 3 |
| PB13 | Create document metadata schema | Nguyen | 2 |
| PB13 | Implement processing status tracking | Nguyen | 2 |
| PB13 | Display metadata and processing status | Nguyen | 2 |
| PB14 | Implement document list and search | Nguyen | 3 |
| PB14 | Implement download and delete actions | Nguyen | 3 |
| PB14 | Test document management integration | Nguyen | 2 |
| SPR1 | Integrate Sprint 1 modules and resolve conflicts | All Members | 4 |
| SPR1 | Run regression tests and fix defects | All Members | 2 |
| SPR1 | Conduct Sprint Review and Retrospective | All Members | 2 |

### Sprint 1 completion rules

- Do not mark a PB/US complete merely because coding exists.
- Verify acceptance criteria, integration, tests, and evidence.
- Record actual effort/progress in the Sprint workbook rather than estimating retrospectively from memory.
- Keep Git/task-board evidence for each member.

## 15. Budget

- Working effort: **860 person-hours × USD 2/hour = USD 1,720**.
- Other project cost: **4 members × USD 100/member = USD 400**.
- Current Project Plan total estimated cost: **USD 2,120**.

Note: an earlier Proposal version used a different cost estimate. For current planning, use the newer Project Plan value unless formally revised again.

## 16. Project Risks

| Risk | Probability | Impact | Exposure | Mitigation |
|---|---:|---:|---:|---|
| Inaccurate effort estimates | 4 | 4 | 16 | Monitor actual effort; break down tasks into larger ones; review estimates in Sprint Planning; adjust Sprint Backlog as needed. |
| Changes or missing requirements | 3 | 4 | 12 | Define scope using proposals; manage change via product backlog; confirm acceptance criteria before development. |
| Technical difficulties with RAG/AI | 5 | 5 | 25 | Implement technical spikes early; build baselines beforehand; log experiments; test with datasets/ground truth; have fallback plans. |
| Unstable document format | 4 | 4 | 16 | Clearly define supported formats; build diverse test data; validate processing status; handle errors and retry appropriately. |
| Uneven programming experience | 4 | 3 | 12 | Pair programming; code review; research spikes; break tasks into smaller parts and share knowledge in Daily Scrum. |
| Conflicts or lack of team coordination | 3 | 3 | 9 | Daily Scrum, Sprint Retrospective, transparent tasks and workload; address blockers early. |
| Frontend/backend/AI integration error | 4 | 4 | 16 | Finalize API contracts early; continuous integration testing; implement vertical slicing from Sprint 2. |

Highest documented risk: **Technical difficulties with RAG/AI — exposure 25 (5×5)**.

## 17. Testing / Quality Expectations

Testing should cover, as appropriate:

- Unit tests.
- API tests.
- Integration tests.
- System/functional tests.
- Regression tests.
- Authorization/RBAC tests.
- Document-processing error handling.
- Structured-output/schema validation.
- AI/RAG quality evaluation using research dataset/ground truth.
- Bug recording, severity/priority, fix, retest.

Definition-of-done style principle: implementation + integration + test evidence + acceptance criteria, not code alone.

## 18. Known Document Inconsistencies / Do Not Silently Resolve

These items should be reconciled in future document revisions:

1. **Project title inconsistency:** Project Plan project-information table uses `Teaching And Learning Support System Based On The Flipped-Learning Method`, while covers/User Story use `FLTS: A RAG-Based Learning Content Generation Platform for Flipped Learning`. Prefer the latter as the current public title unless the mentor instructs otherwise.
2. **User Story version display:** cover shows Version 1.0/date 2026-09-22, but revision history contains v1.1 dated 2026-09-24 correcting PB↔US traceability. Treat v1.1 mapping as current.
3. **Budget supersession:** older Proposal estimate differed; current Project Plan states USD 2,120.
4. **Vector DB:** this was historically TBD; the later 2026-10-02 decision in section 28 selects local ChromaDB for Sprint 2.
5. **AI model/provider:** this was historically TBD, and the OpenAI direction recorded on 2026-09-26 was later superseded by the Google Gemini decision in section 28.
6. **Research dataset:** source direction is DTU teaching materials per conversation, but exact corpus/sample/ground-truth procedure remains to be documented.
7. **Sprint Actual data:** uploaded Sprint 1 workbook has planned values but no actual task progress filled in yet.

## 19. Current State Snapshot — 2026-09-25

- Start-up & Requirements phase has ended according to the plan (through 2026-09-24).
- Sprint 1 — Foundation starts **2026-09-25** and ends **2026-10-08**.
- Current Sprint 1 plan includes repository/branching, Docker environment, coding/quality standards, CI/test foundation, registration/login/password recovery, RBAC/account management, course management/access, document upload/metadata/management, integration/regression, Sprint Review/Retrospective.
- Sprint 1 planned scope is 128 hours, 32 hours/member.
- Actual workbook is not yet populated; do not claim completed tasks without new evidence.

## 20. Next Major Technical Milestones

- Sprint 1: foundation/auth/course/document-upload.
- Sprint 2: PDF/DOC/DOCX extraction, cleaning, chunking, embeddings, vector storage, similarity retrieval, re-ranking/context optimization, RAG prototype.
- Sprint 3: Quiz + Flashcard generation, structured output validation, lecturer review/edit/regenerate/approve/publish, Student workspace, quiz interaction.
- Sprint 4: Mindmap + Interactive Slide, learning-event tracking, analytics, dataset/ground truth, baseline/proposed evaluation, ablation/reproducibility.
- QA: system tests, AI quality tests, regression, bug fixing, deployment verification.
- Final Release: final validation, research results, documentation, demo, submission.

## 21. Decision Log — Confirmed vs TBD

### Confirmed

- Four-person team.
- Scrum.
- Vue 3 + Vite frontend.
- Laravel/PHP application backend.
- Python/FastAPI AI service.
- MySQL relational database.
- Docker/Docker Compose direction.
- PDF/DOC/DOCX input scope.
- Four target learning objects.
- Human-in-the-Loop publication.
- Basic analytics only.
- Research requires dataset, ground truth, baseline/proposed comparison, metrics, logs, ablation.
- Dataset source direction: authorized DTU teaching materials (chat-confirmed).
- Sprint 2 AI provider: Google Gemini API.
- Sprint 2 embedding model: `text-embedding-004` with 768 dimensions.
- Sprint 2 generation model: `gemini-1.5-flash` with Structured Outputs / JSON Schema validation required.
- Sprint 2 vector store: local ChromaDB.

### TBD / must not be invented

- Exact parsing libraries.
- Exact chunking strategy and parameters.
- Exact retrieval similarity metric/top-k.
- Whether/how re-ranking is implemented in MVP.
- Exact research baselines and proposed method.
- Exact dataset size and annotation process.
- Exact evaluation metrics/thresholds.
- Hosting/deployment target.

## 22. How the Next AI Agent Should Continue

Before doing work:

1. Identify the artifact or Sprint being changed.
2. Check this master context.
3. Check the latest relevant source file (Sprint workbook, User Story, Project Plan, Proposal).
4. Preserve PB↔US traceability from User Story v1.1.
5. If implementing a story, use its acceptance criteria as minimum done conditions.
6. If implementing AI/RAG, log parameters/configurations so research can be reproduced later.
7. If changing scope/architecture/technology, record it as a decision and update affected documents/backlogs.
8. Never present a planned feature as implemented without evidence.

When handing off again, update at least:

- Snapshot date.
- Current Sprint and completion status.
- Actual task progress.
- Technical decisions made.
- Research decisions made.
- New risks/blockers.
- Changes to PB/US/acceptance criteria.
- Links/paths to source code and deployed environment once they exist.

## 23. Recommended Repository Context Files

For AI-assisted implementation, keep this file in the project root, e.g. `PROJECT_CONTEXT.md`. Consider adding:

- `README.md` — setup and developer onboarding.
- `docs/architecture.md` — current architecture and API boundaries.
- `docs/decisions/ADR-*.md` — architecture/technology decisions.
- `docs/research/experiment-log.md` — experiment configurations/results.
- `docs/research/dataset-card.md` — dataset sources, authorization, selection, version, ground truth.
- `docs/testing/test-strategy.md` — testing scope and evidence.

---

## Final Reminder for AI Agents

FLTS is not 'an AI that replaces lecturers'. It is a document-grounded learning-content generation platform where AI produces drafts, lecturers retain academic control, students consume only published content, and the RAG pipeline is evaluated experimentally rather than assumed to be accurate.

## 24. Implementation Update — 2026-09-26

### Repository verification at the start of this work

- The repository root contained only this context file and `docs/reference/`; it did **not** contain application source, Docker configuration, README, or a Git repository (`git status` and `git log` returned “not a git repository”).
- `docs/reference/` contains `C1SE32_UserStory_FTLS_ver1.1.docx`, `C1SE32_FLTS_Sprint1_Capstone.xlsx`, `C1SE32-Project Plan_FLTS_ver1.0.docx`, and `C1SE32-Proposal_FLTS_ver1.1.docx`.
- The User Story revision history lists v1.1 dated 2026-09-24, correcting PB–US traceability. The Sprint 1 workbook spans 2026-09-25 to 2026-10-08 and its `Actual` sheet has no entered task actuals at inspection time. Planned charts must not be reported as actual progress.
- Project Plan v1.0 has an older PB–US numbering in its planning table (from PB15 onward). The User Story v1.1 mapping remains the source used for new implementation and reporting. This is a documented conflict, not a retroactive change to the Project Plan.

### Demo implementation added on 2026-09-26

- Added a Docker Compose foundation with Vue 3 + Vite/Nginx (`frontend/`), Laravel/PHP (`backend/`), FastAPI (`ai-service/`), and MySQL.
- Initialized the local repository with branch `main`; added `docs/DEVELOPMENT.md` with a proposed branch/review convention and a GitHub Actions CI workflow. Remote access, all-member access, branch protection, PR review and CI execution have not been verified and remain team/hosting actions.
- Added Laravel migrations and demo seed accounts for Lecturer, Student, and Administrator. The seeded Student is enrolled in seeded course `FLIP-101`.
- Added API authentication using a hashed bearer token for the local demo: registration for Lecturer/Student, login, authenticated profile, logout token invalidation; backend role/ownership checks; Lecturer course CRUD endpoints; enrollment endpoint; and document upload/list/download/delete endpoints.
- Upload accepts PDF/DOC/DOCX and enforces a configurable 10 MB maximum. `uploaded_pending_processing` accurately means that the file was stored with metadata but no extraction, chunking, embedding, vector storage, retrieval, or RAG processing has occurred.
- Added a deliberately limited Vue demo UI for login/logout, Lecturer course creation/viewing, document upload/list/delete, and Student course visibility. It does not present unimplemented learning-object, RAG, quiz, or analytics features.
- Added Laravel feature tests for login/logout token invalidation, Student access restrictions, and document-upload authorization/validation. Test execution evidence must be recorded after Docker execution; code presence alone is not Sprint completion.

### Verification results on 2026-09-26

- `docker compose config --quiet` passed. `docker compose up --build -d` could not start because the local Docker daemon pipe `//./pipe/docker_engine` was unavailable. No Docker/MySQL/Nginx end-to-end claim has been made.
- PHP syntax lint passed for all files under `backend/`; FastAPI compile check passed; Vue production build passed.
- Laravel feature tests passed locally with PHP 8.4 after enabling its bundled SQLite/fileinfo extensions for that process only: **4 tests, 19 assertions**. These cover login/logout token invalidation, Lecturer course creation, Student authorization and granted enrollment access, owner-only document upload/list/delete, invalid-extension rejection, and size validation.
- The API/UI must still be rechecked through Docker Desktop, including actual MySQL migration/seed, service health endpoints, and browser interactions, before reporting a fully integrated increment.

### New blockers and follow-up actions

- Docker Desktop was installed but its daemon was not available during this implementation, so Compose build/up and browser/MySQL integration remain unverified.
- The local `.git` initialized during this work is owned by `CodexSandboxOffline`. An attempted scoped ownership transfer to Windows account `ASUS` was denied. A user with appropriate Windows permissions must correct ownership or configure Git safe-directory trust before normal Git operations under their account.
- `npm install` reported two dependency audit vulnerabilities (one moderate, one high). No forced automatic upgrade was performed; the team should review the audit output deliberately.

### Additional implementation choice

- The demo backend locks a current Laravel 12 dependency set and targets PHP 8.3 in its Docker image. This is an implementation baseline for the demo, within the confirmed Laravel/PHP stack; it does not decide any of the still-TBD AI/RAG technologies.

### Temporary demo choices — not project decisions

- The 10 MB upload limit and simple single bearer-token-per-user approach are local-demo implementation choices only. They do not select a production authentication/session design.
- The FastAPI service exposes only `/health` and explicitly states that extraction/RAG are Sprint 2 work.
- No LLM provider/model, embedding model, parsing library, or vector database has been selected by this implementation.

## 25. Verified Docker and Frontend Refactor Update — 2026-09-26

### Docker verification and runtime corrections

- Docker Compose was subsequently rebuilt and verified: `api`, `ai`, and `web` are running; `mysql` is running and healthy. Laravel `/up` and FastAPI `/health` returned HTTP 200.
- The API build required `libsqlite3-dev` and `libonig-dev`. Composer dependencies in the current lockfile require PHP `>= 8.4.1`, so the demo runtime is `php:8.4-cli` rather than PHP 8.3. This corrects demo compatibility; it does not change the confirmed Laravel/PHP stack direction.
- PHP/Nginx upload limits now support the existing Laravel 10 MB file validation: PHP accepts a 10 MB file / 11 MB multipart body and Nginx permits an 11 MB body for multipart overhead. Laravel remains the authority enforcing the 10 MB file limit.

### Frontend refactor and verified flows

- The single `frontend/src/App.vue` was refactored into Vue Router routes/guards, auth/toast stores, API services, reusable components, layouts, views, and organised assets. The Laravel API contract was retained; no mock RAG/quiz/analytics features were added.
- Lecturer Login/dashboard, course creation, Course Detail metadata display, Student restricted course list, and logout-to-Login were verified in the browser against the running Docker stack.
- A 2.5 MB DOCX upload through the Nginx `/api` proxy returned HTTP 201 and persisted `uploaded_pending_processing`. The Course Detail UI displayed its name, MIME type, size, date, and Pending processing status.
- `npm run build` passed with Vue Router (50 Vite modules transformed). `docker compose exec -T api php vendor/bin/phpunit` passed: 4 tests and 19 assertions.
- Browser file-chooser automation selected files but did not dispatch a page change event in this environment. The actual multipart proxy/API upload and UI display of its persisted metadata were verified separately. This is an automation limitation, not a claim that the automated file-picker interaction passed.

### Test data and remaining scope

- Integration testing created Lecturer-owned course `UI-2609 — Sprint 1 Interface Review` and stored one copy of the User Story DOCX. A now-corrected PHPUnit isolation defect subsequently cleared that temporary data; the current demo database was seeded again. This is not completion evidence or a claim of team contribution.
- PHPUnit initially inherited Docker MySQL environment variables despite SQLite entries in `phpunit.xml`; `RefreshDatabase` could therefore empty demo tables. Test bootstrap now forces SQLite in-memory before any test connection resolves. A subsequent passing PHPUnit run (4 tests, 19 assertions) preserved the Lecturer and Student seed accounts, and Lecturer UI login was reverified.
- Administrator management UI, UI enrollment management, password recovery, document processing, RAG, learning-object workflows, analytics, and research evaluation remain outside the completed evidence for this increment.

## 26. Sprint 2 AI Provider Decision — 2026-09-26

> **Superseded on 2026-10-02:** section 28 replaces this OpenAI direction with the confirmed Google Gemini + local ChromaDB decision. This section is retained as decision history.

- The project lead selected the **OpenAI API direction** for the Sprint 2 RAG vertical slice. This supersedes the previously open provider decision only; it does not make Sprint 2 functionality complete.
- Planned embedding model: `text-embedding-3-small` (default 1536 dimensions unless a documented, tested dimension reduction is adopted).
- Planned generation model: `gpt-4.1-mini`, with Structured Outputs/JSON Schema validation required before US-38/PB23 can be reported as complete.
- An OpenAI Platform API key and billing/usage limit are required; a ChatGPT subscription is not treated as an API credential. The key must exist only in each developer's ignored local `.env`, never in Git, source code, screenshots, or documentation.
- ChromaDB remains a separate unresolved decision: the Sprint 2 workbook names local ChromaDB, while the master context historically marked the vector database TBD. Confirm it explicitly before PB20 and pin its image/client version.
- This decision does not authorize adding an LLM SDK or cloud credentials before the relevant PB is started through the normal branch/PR process.

## 27. Sprint 1 Closeout Implementation Update — 2026-09-26

This is a repository-verified implementation update, not an assertion that every planned hour, reviewer action, or Sprint workbook `Actual` entry has been completed.

- Branch `feature/sprint1-closeout` contains the closeout implementation. The GitHub main branch, remote CI and a PR-based ruleset had already been verified separately; the new `backend-quality` CI job still needs one remote PR run before it can be selected as a required GitHub status check.
- Compose now includes local Mailpit (`http://localhost:8025`) for the demo password-reset email flow. API and AI health checks returned `ok`; `api`, `ai`, `web`, `mysql`, and `mailpit` were observed running/healthy after `docker compose up --build -d`.
- Registration UI/API validation, reset-token email/reset flow, Admin account list/role/status management, Lecturer course update, document search, and document download UI/service were implemented without changing the existing course/document API ownership model.
- New database migrations add `users.account_status` and `password_reset_tokens`. Account suspension is enforced by both login and token middleware; it revokes an existing token. The reset token is stored hashed, expires in one hour, and is deleted after successful use.
- Docker PHPUnit execution passed **7 tests and 49 assertions**. Browser verification logged successful Lecturer, Administrator, and Student login/route outcomes. A Mailpit API check observed one reset email after a real local reset request. `frontend` production build and PHP syntax checks also passed in this working session.
- PB13/US-13 must remain **partial**: metadata and accurate `uploaded_pending_processing` display exist, but no extractor/worker/pipeline can transition status or persist a processing error. Those missing acceptance criteria are explicit Sprint 2 PB13 carry-over, not a completed RAG claim.
- The local Excel lock file pattern `docs/reference/~$*.xlsx` is ignored. No source Word/Excel plan was edited and no `Actual` time was filled.
- The canonical current handoff/status document is `docs/SPRINT_1_CLOSEOUT_STATUS.md`.

## 28. Sprint 2 Gemini and Vector Store Decision — 2026-10-02

- The project lead formally selected the **Google Gemini API** for the Sprint 2 RAG vertical slice, superseding the OpenAI direction recorded in section 26.
- Embedding model: `text-embedding-004`, with an expected vector dimension of **768**. The implementation must validate the returned dimension and persist provider/model/dimension metadata with processing runs and vector references.
- Generation model: `gemini-1.5-flash`. Structured Outputs / JSON Schema validation and explicit generation-error handling remain required before PB23/US-38 can be reported as complete.
- Vector store: **local ChromaDB** in Docker Compose with a persistent volume, healthcheck, pinned image/client versions, deterministic vector IDs and lifecycle handling for retry/reprocess/delete.
- Python SDK direction: `google-genai`. Exact package versions for `google-genai`, `pypdf`, `python-docx`, `chromadb`, `httpx`, `pydantic-settings` and `pytest` must be pinned when the implementation branch starts.
- The Gemini API key must exist only in an ignored local `.env` and be passed to the AI container through environment variables. Never commit, log, screenshot or copy the key into Markdown, source, fixtures or CI output.
- This decision selects provider/models/vector store only. Queue design parameters, parser versions, legacy DOC strategy, chunk size/overlap, retrieval top-k/metric, re-ranking scope, rate limits, retry/backoff and evaluation thresholds remain to be confirmed or tested.

## 29. Sprint 2 implementation checkpoint — 2026-10-05

This is a repository/runtime checkpoint, not a claim that Sprint 2 or its workbook PBs are complete.

- The branch `feature/sprint2-rag-vertical-slice` adds `queue-worker` and local ChromaDB to Compose. `api` now has a healthcheck; the worker waits for it, preventing concurrent migration races.
- New MySQL tables are `jobs`, `failed_jobs`, `document_processing_runs`, `document_extractions`, `document_chunks`, and `document_vector_references`. `teaching_documents` stores its latest run and processed timestamp. Every run records attempt/status/stage/config/error; chunks retain deterministic hashes/source locators and link to Chroma vector IDs.
- Laravel now authorizes Lecturer ownership before trigger/retry/status/retrieval/evidence endpoints. It dispatches a database job; the job sends the stored source to FastAPI, persists extraction/chunk/vector references only after a successful result, and presents a retry-safe failure state otherwise.
- FastAPI now parses text-based PDF (`pypdf`) and DOCX (`python-docx`), normalizes and paragraph-chunks text, uses Gemini `text-embedding-004` with 768-dimension validation, stores/retrieves vectors from local ChromaDB, and requests structured grounded evidence from `gemini-1.5-flash`. Browser clients never receive the Gemini key or internal service token.
- Legacy binary `.doc` is now extracted through the lightweight `antiword` parser in the AI container, with a 30-second time limit and explicit failure code. This is a Sprint 2 implementation choice; fidelity still needs a real permitted DOC fixture. Scanned/empty/encrypted PDFs likewise do not become false successful processing runs.
- Actual runtime verification on 2026-10-05: all seven services (`api`, `queue-worker`, `ai`, `chroma`, `mysql`, `mailpit`, `web`) were observed Up; API, AI, MySQL, Chroma and Mailpit were healthy. Laravel PHPUnit passed **15 tests, 80 assertions**; FastAPI tests passed **9 tests**, including a real local Chroma process/retrieve/course-filter/delete lifecycle test using deterministic test vectors (not a Gemini claim) and a missing-service-token rejection. A Chroma persistence probe retained one 768-dimension vector across a Chroma restart and then removed the probe collection. The unauthenticated internal endpoint returned 401 as expected.
- Lifecycle hardening verified in Laravel tests: a document cannot be deleted while its queue job is processing; every terminal deletion calls vector cleanup first; public retrieval errors do not return FastAPI/provider diagnostics. This does not replace real Gemini E2E evidence.
- `AI_SERVICE_TOKEN` no longer has a predictable Compose default. Both environment examples leave it blank intentionally; each local stack must set an uncommitted high-entropy value before internal RAG calls can work.
- The Laravel queue-job contract test verifies that document processing is sent to FastAPI as `multipart/form-data`; JSON is now limited to retrieval/evidence requests. This removes the pre-E2E content-type mismatch that would otherwise prevent FastAPI from receiving the source file.
- The Gemini key pasted into a chat must be treated as exposed and rotated. No key was written to source, Compose, documentation, or Git. A new key still needs to be placed only in ignored local `.env` before a real Gemini/Chroma end-to-end run can be claimed.
- PB13 is implemented and test-covered at API/state level but still needs real-provider queue evidence. PB15/PB16/PB17/PB18 have a functional baseline but lack the workbook fixture matrix; PB16 still needs a real permitted binary DOC fixture to establish parser fidelity. PB19/PB20/PB21/PB23 have code/infrastructure but are **not complete** until a rotated key enables successful end-to-end embedding, persistent-vector restart, retrieval isolation, and structured evidence/citation evidence.
- Repository verification on 2026-10-02 found `main` clean at merge commit `371ddca`; PR #4 (Google Mail SMTP/WelcomeMail) and PR #5 (Vietnamese production UI) are merged. Docker showed `api`, `web`, `mysql`, `mailpit`, and `ai` running, with MySQL/Mailpit healthy. PHPUnit passed **7 tests and 50 assertions**.

## 30. Gemini provider verification and rate-limit hardening — 2026-10-05

This section records a later runtime/configuration update. It supersedes the **implementation defaults** in section 28, but does not erase that earlier planning decision or mark Sprint 2/workbook PBs complete.

- The local AI health endpoint was observed with `gemini-embedding-2` (validated 768-dimensional vectors) and `gemini-2.5-flash`. `text-embedding-004` and `gemini-1.5-flash` remain the 02/10 planning history; the running SDK/API compatibility result required the new model defaults.
- The document embedding path now sends `types.Content` items in batches of 40. On Gemini `429`/`RESOURCE_EXHAUSTED`, it parses the provider delay, bounds it to 15–60 seconds, and retries each batch at most 15 times. Batch size/retry count are environment-configurable; Laravel FastAPI calls and the queue worker currently allow 600 seconds.
- Current local evidence: run 14/document 5 (PDF) is `processed` with 294 chunks and 294 vector references; run 15/document 2 (PDF) is `processed` with 257 chunks and 257 vector references. Chroma currently retains matching vectors/metadata. FastAPI internal retrieval logs show successful requests.
- Antigravity's handoff reports a permitted DOCX (document 4) with 46 chunks, retrieval and grounded evidence generation. That document was later deleted, so no current DB/Chroma record exists for independent verification; it must be repeated with a permitted DOCX before PB16 or the whole RAG vertical slice is presented as complete.
- After this configuration hardening, Docker remained healthy; Laravel PHPUnit passed **15 tests / 80 assertions**, and FastAPI passed **13 tests** (one non-blocking Starlette deprecation warning). New FastAPI regression coverage verifies 40/40/1 batching, preserved vector order, 429 retry timing and safe provider-error reporting.
- A fresh Lecturer UI retrieval/evidence-generation capture, real-document Chroma-restart retrieval, error/authorization fixture matrix, and legacy binary DOC fixture are still required. OCR, re-ranking, quality evaluation, quiz, publish workflow and analytics remain out of scope for this vertical slice.
- `GEMINI_API_KEY` and `AI_SERVICE_TOKEN` remain ignored local secrets. Do not copy a key from chat or logs into source/Markdown/Git; rotate any key that was previously shared outside the approved secret store.

## 31. Sprint 2 RAG evidence-response correction — 2026-10-05

This is an implementation and runtime-verification update, not a retrospective claim that every Sprint 2 PB is Done.

- The Lecturer's **Create evidence-backed response** request previously returned an Nginx 504 after a long-running AI request, then an internal 502 with `UNGROUNDABLE_CITATION`. The 504 was an upstream read timeout; the 502 was caused by treating Gemini-generated citation strings as if they were pre-existing Chroma vector IDs.
- The AI contract now asks Gemini to return only `citation_indexes` into the supplied, numbered retrieved sources. FastAPI validates that every index is in range, then maps it server-side to the actual vector ID, document metadata and source locator. This preserves grounded citations without trusting an LLM to invent database identifiers.
- Retrieval and generated-context text redact email addresses before it reaches the Lecturer UI or the generation prompt. The cleaner also detects a page/table/paragraph locator anywhere in an overlapped chunk, rather than only at its first character.
- Interactive retrieval/evidence queries fail fast with a safe HTTP 429 if Gemini embedding quota is temporarily exhausted; document-processing batches still use bounded retry. Laravel converts internal/provider details into safe user-facing 422/429/503 messages. Nginx permits the documented asynchronous RAG window rather than its default 60-second proxy read timeout.
- Runtime verification after recreating `api`, `queue-worker`, `web`, and `ai`: all seven Compose services were running (API, AI, MySQL, Chroma and Mailpit healthy); a real authenticated internal evidence request returned `EVIDENCE_OK` with two citations. Regression results: Laravel PHPUnit **16 tests / 83 assertions**, FastAPI **16 passed** with one non-blocking Starlette deprecation warning.
- This confirms the current PDF-backed RAG vertical-slice evidence API can complete with the configured local provider. It does **not** replace the remaining Sprint Review evidence: a new permitted DOCX run retained in DB/Chroma, real-document Chroma restart retrieval, corrupt/empty fixture evidence, cross-account UI capture, and the team review/PR evidence specified in the Sprint workbook.

## 32. Sprint 3 Quiz Learning Object implementation checkpoint — 2026-10-06

This records implementation and local runtime evidence on branch `feature/sprint3-quiz-learning-objects`; it does not replace PR/CI/review or team Sprint acceptance.

- The core Quiz vertical slice implements PB24, PB28, PB29, PB30, PB31, PB33, PB34, PB35 and PB36. PB25 Flashcards and PB32 Regenerate remain unimplemented stretch goals.
- Seven MySQL tables now represent learning objects, immutable version snapshots, quiz content and retained Student attempts/answers. Draft edit creates a new version; published content is read-only; `draft`, `published` and `archived` access is enforced in Laravel, not merely hidden in Vue.
- FastAPI `POST /internal/v1/generation/quiz` retrieves only course/document-scoped Chroma chunks, asks Gemini for structured `citation_indexes`, validates the complete schema and maps indexes server-side to authoritative vector/source metadata. The browser never receives the internal token or Gemini key.
- Vue adds Lecturer generate/preview/edit/publish views and a Student published-course/Quiz workspace. Before submit, Student responses omit answer keys/explanations/citations; after submit they include correctness, the right option, grounded explanation and citations.
- Retakes are unlimited by current policy. Every completed attempt remains stored; API/UI report latest and best scores.
- Runtime evidence on 06/10: 7 Compose services running; migrations through `000020` ran without deleting data; Chroma remained at 551 vectors; a real provider call created 3 grounded questions from 5 retrieved chunks using `gemini-2.5-flash`; PHPUnit passed 23 tests/136 assertions; Pytest passed 19 tests. Browser E2E completed Lecturer generate → version 2 → publish and Student attempt 1 (33.33) → retake (100).
- Canonical implementation evidence and remaining limitations are in `docs/SPRINT_3_EXECUTION_STATUS.md`.
