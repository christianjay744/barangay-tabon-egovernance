const express=require('express');
const cors=require('cors');
const {ethers}=require('ethers');
const fs=require('fs');

const app=express();app.use(cors());app.use(express.json());
let contractAddress=process.env.CONTRACT_ADDRESS||'';
const abi=[
"function recordDocument(string documentNumber, bytes32 documentHash) external",
"function verifyDocument(string documentNumber, bytes32 documentHash) external view returns (bool valid,bool revoked,uint256 timestamp)"
];
const provider=new ethers.JsonRpcProvider('http://127.0.0.1:8545');
let wallet,contract;
function setup(){
 try{
  wallet=new ethers.Wallet(process.env.PRIVATE_KEY||'0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf6f2c4e',provider);
  if(contractAddress) contract=new ethers.Contract(contractAddress,abi,wallet);
 }catch(e){console.log(e.message)}
}
setup();

app.get('/health',(req,res)=>res.json({ok:true,contractAddress}));
app.post('/record',async(req,res)=>{
 try{
  if(!contract) return res.status(503).json({success:false,error:'Contract not configured. Deploy first.'});
  const {documentNumber,hash}=req.body;
  const tx=await contract.recordDocument(documentNumber,'0x'+hash);
  await tx.wait();
  res.json({success:true,tx:tx.hash});
 }catch(e){res.status(500).json({success:false,error:e.shortMessage||e.message})}
});
app.post('/verify',async(req,res)=>{
 try{
  if(!contract) return res.status(503).json({success:false,error:'Contract not configured'});
  const {documentNumber,hash}=req.body;
  const r=await contract.verifyDocument(documentNumber,'0x'+hash);
  res.json({success:true,valid:r[0],revoked:r[1],timestamp:r[2].toString()});
 }catch(e){res.status(500).json({success:false,error:e.shortMessage||e.message})}
});
app.listen(3001,()=>console.log('Blockchain API: http://127.0.0.1:3001'));
