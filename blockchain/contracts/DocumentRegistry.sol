// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract DocumentRegistry {
    struct Record { string documentNumber; bytes32 documentHash; uint256 timestamp; bool revoked; }
    mapping(string => Record) private records;
    event DocumentRecorded(string documentNumber, bytes32 documentHash, uint256 timestamp);
    event DocumentRevoked(string documentNumber, uint256 timestamp);

    function recordDocument(string calldata documentNumber, bytes32 documentHash) external {
        records[documentNumber] = Record(documentNumber, documentHash, block.timestamp, false);
        emit DocumentRecorded(documentNumber, documentHash, block.timestamp);
    }

    function revokeDocument(string calldata documentNumber) external {
        require(bytes(records[documentNumber].documentNumber).length > 0, "Document not found");
        records[documentNumber].revoked = true;
        emit DocumentRevoked(documentNumber, block.timestamp);
    }

    function verifyDocument(string calldata documentNumber, bytes32 documentHash)
        external view returns (bool valid, bool revoked, uint256 timestamp) {
        Record memory r = records[documentNumber];
        return (r.documentHash == documentHash && r.documentHash != bytes32(0), r.revoked, r.timestamp);
    }
}