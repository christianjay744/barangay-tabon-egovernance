# Blockchain-Based E-Governance Document Verification and Digital Records Management System for Barangay Tabon
## 30% Running Prototype

### Included in this 30%
1. User registration and login
2. Resident profile management
3. Document request submission
4. Requirement/notes submission
5. Resident request tracking
6. Admin dashboard
7. Admin approval/rejection
8. Digital document generation (HTML document)
9. SHA-256 document hash generation
10. Blockchain recording through a Node.js API
11. QR-code verification link
12. Public document verification
13. Basic activity/audit records

### Technology
- Frontend/backend: PHP 8+ / HTML / CSS
- Database: MySQL/MariaDB
- Local server: XAMPP
- Blockchain prototype: Node.js + Hardhat + Solidity
- QR: remote QR image service for prototype

> This is a capstone prototype, not a production government system. Do not put real sensitive resident data on a public blockchain.

## 1. Install XAMPP
Start Apache and MySQL.

Copy this folder to:
C:\xampp\htdocs\barangay_blockchain_30

Open phpMyAdmin and import:
database/schema.sql

## 2. Configure PHP database
Edit:
config/db.php

Default XAMPP settings:
host = localhost
user = root
password = empty
database = barangay_blockchain

## 3. Run the PHP system
Open:
http://localhost/barangay_blockchain_30/

Default admin:
Email: admin@barangaytabon.local
Password: admin123

A resident can register from the Register page.

## 4. Optional blockchain module
Install Node.js.

Open a terminal inside:
blockchain/

Run:
npm install
npx hardhat node

In another terminal:
npm run deploy

Then start the API:
npm run api

The PHP application sends document hashes to:
http://127.0.0.1:3001/record

If the blockchain API is not running, the system still stores the hash in MySQL and labels the blockchain status as "PENDING". This makes the prototype easier to demonstrate.

## 5. Demo flow
Resident:
Register -> Login -> Profile -> Request document -> Track request

Admin:
Login -> Dashboard -> Open request -> Approve -> Generate/record document

Verification:
Use the verification link/QR -> enter document number -> compare hash/status

## 30% scope mapping
INPUT:
registration, identity information, document request, requirements, authentication

PROCESS:
authentication, validation, review, approval, document generation, hashing, blockchain recording, QR generation, verification

OUTPUT:
verified accounts, resident records, processed requests, digital documents, hashes, blockchain transaction IDs, QR verification, authenticity/status result, activity records
