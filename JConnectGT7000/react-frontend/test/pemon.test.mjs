import assert from 'node:assert/strict'
import {readFile} from 'node:fs/promises'
import {test} from 'node:test'
import ts from 'typescript'

const source=await readFile(new URL('../src/features/devices/pemon.ts',import.meta.url),'utf8')
const {outputText}=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}})
const {PEMON_METRICS,pemonOID,appendPemonMetrics,pemonConflict}=await import('data:text/javascript;base64,'+Buffer.from(outputText).toString('base64'))
const fixture=JSON.parse(await readFile(new URL('../../PEMON Test/data/last-snmp.json',import.meta.url),'utf8'))
const comparison=JSON.parse(await readFile(new URL('../../PEMON Test/data/last-snmp-ui-compare.json',import.meta.url),'utf8'))

test('each generated OID and multiplier matches PEMON test evidence',()=>{
 assert.equal(PEMON_METRICS.length,17)
 assert.equal(new Set(PEMON_METRICS.map(pemonOID)).size,17)
 for(const d of PEMON_METRICS){
  assert.match(pemonOID(d),/^1\.3\.6\.1\.4\.1\.12807\.3\.1\.1\.\d+\.\d+\.0$/)
  const evidence=d.source==='decoded'?fixture.decoded.mapped.find(m=>m.known&&m.oid===pemonOID(d)):comparison.checks.find(m=>m.match&&m.suffix===d.suffix&&typeof m.snmp_raw==='number')
  assert.ok(evidence,`Missing evidence: ${d.key}`)
  const raw=d.source==='decoded'?evidence.raw:evidence.snmp_raw
  const scaled=d.source==='decoded'?evidence.value:evidence.scaled
  assert.equal(typeof raw,'number')
  assert.ok(Math.abs(raw*d.scale-scaled)<1e-9,`Invalid scale: ${d.key}`)
 }
 assert.ok(!PEMON_METRICS.some(d=>d.suffix==='1.23'),'Text System Name must not enter numeric SNMP pipeline')
})
test('append preserves existing config and skips matching keys and normalized OIDs',()=>{
 const d=PEMON_METRICS[0]
 const existing=[{key:'custom',oid:'.'+pemonOID(d),scale:123,triggers:[{message:'keep me'}]}]
 const before=JSON.stringify(existing)
 const result=appendPemonMetrics(existing,PEMON_METRICS.map(m=>m.key),30)
 assert.equal(result.added,16);assert.equal(result.skipped,1)
 assert.equal(JSON.stringify(existing),before);assert.equal(result.metrics[0],existing[0])
 assert.ok(pemonConflict([{key:d.key,oid:'1.2.3'}],d))
 assert.ok(pemonConflict([{key:'alias',snmp_oid:'.'+pemonOID(d)}],d))
 const again=appendPemonMetrics(result.metrics,PEMON_METRICS.map(m=>m.key),30)
 assert.equal(again.added,0);assert.equal(again.skipped,17)
})
test('selected metrics use collector-compatible numeric fields',()=>{
 const {metrics}=appendPemonMetrics([],['dc.high_float_v'],45)
 assert.equal(metrics.length,1)
 assert.equal(metrics[0].pull_rate,45);assert.equal(metrics[0].type,'snmp_get');assert.equal(metrics[0].scale,0.01)
 assert.equal(metrics[0].protocol,'snmp');assert.equal(metrics[0].datatype,'float')
 assert.ok(metrics[0].enable);assert.deepEqual(metrics[0].triggers,[])
 assert.throws(()=>appendPemonMetrics([],[],0));assert.throws(()=>appendPemonMetrics([],[],3.5));assert.throws(()=>appendPemonMetrics([],[],3601))
})
