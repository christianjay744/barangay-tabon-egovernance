const hre = require("hardhat");
async function main(){
 const c=await hre.ethers.deployContract("DocumentRegistry");
 await c.waitForDeployment();
 console.log("CONTRACT_ADDRESS="+await c.getAddress());
}
main().catch(e=>{console.error(e);process.exitCode=1});