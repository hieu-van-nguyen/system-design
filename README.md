# System Design Implementations & Articles

This repository is dedicated to exploring, implementing, and documenting common system design solutions. The goal is to bridge the gap between theoretical architecture and practical implementation.

## 🎯 Objectives
- **Practical Implementations**: Build working prototypes of system design components (e.g., Rate Limiters, Distributed Caches, Load Balancers).
- **In-depth Articles**: Write detailed markdown articles explaining the "why" behind design decisions, trade-offs, and architectural patterns.
- **Knowledge Base**: Create a searchable and executable reference for system design interviews and real-world engineering.

## 📂 Proposed Structure
To keep the repository organized, the following structure is used:

- `/solutions`: Contains the source code for various system design implementations.
  - Each solution will have its own subdirectory (e.g., `/solutions/rate-limiter`).
- `/articles`: Contains markdown files (`.md`) that provide the theoretical background and design documentation for the solutions.
- `/src`: General utilities and shared logic.

## 🛠️ Getting Started

### Prerequisites
- JDK 24
- Apache Maven

### Building the Project
```bash
mvn clean compile
```

### Running an Implementation
Since this is a Maven project, you can run specific main classes using:
```bash
mvn exec:java -Dexec.mainClass="us.inest.Main"
```
*(Replace `us.inest.Main` with the specific class for the solution you are testing)*

## 📝 Contributing
If you are adding a new system design solution:
1. Create a new directory in `/solutions`.
2. Implement the core logic.
3. Write a corresponding article in `/articles` explaining the design, complexity analysis, and trade-offs.
