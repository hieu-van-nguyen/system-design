# Popular AI System Design Interview Topics at FAANG

## Recommendation Systems

- **News Feed / Content Ranking** (Meta, YouTube) — ML ranking, feature engineering, real-time vs batch
- **Product Recommendations** (Amazon) — collaborative filtering, embeddings, cold start
- **Friend Suggestions** (Meta/LinkedIn) — graph-based ML, network embeddings

## Search & Retrieval

- **Semantic Search / Neural Search** — dense retrieval, ANN indexes (FAISS, ScaNN), re-ranking
- **Query Understanding** — intent classification, entity extraction, query rewriting

## NLP / LLM Systems

- **LLM Serving Infrastructure** — batching, KV cache, quantization, autoscaling
- **RAG Pipeline** — chunking, vector stores, retrieval, context window management
- **Chatbot / Conversational AI** — session management, safety filters, latency

## Ads & Monetization

- **Click-Through Rate (CTR) Prediction** (Meta, Google) — real-time features, online learning
- **Auction Systems** — bid prediction, pCTR × pCVR pipelines

## Vision Systems

- **Image / Video Classification at Scale** (Instagram, YouTube)
- **Object Detection Pipeline** — streaming inference, model versioning

## Core ML Infrastructure

- **Feature Store** — online vs offline, consistency, low-latency serving
- **ML Platform / Training Pipeline** — data ingestion, distributed training, experiment tracking
- **Model Serving / Inference** — A/B testing, shadow mode, canary deploys
- **Data Labeling System** — active learning, human-in-the-loop

---

## What Interviewers Actually Evaluate

| Area | Key Questions |
|------|--------------|
| **Data** | How do you collect, label, and handle drift? |
| **Modeling** | Which model? Why? Trade-offs? |
| **Scale** | QPS, latency SLAs, throughput |
| **Feedback loops** | Online metrics, A/B testing, retraining triggers |
| **Failure modes** | Cold start, bias, data poisoning |

---

## Top Resources

- *Designing Machine Learning Systems* — Chip Huyen
- *Machine Learning System Design Interview* — Ali Aminian & Alex Xu
- `educative.io/courses/machine-learning-system-design` (grokking series)
