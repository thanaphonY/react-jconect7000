export interface Health {status:'ok'; database:'connected'; mode:'read-only'}

export async function getHealth(signal?:AbortSignal):Promise<Health>{
 const response=await fetch('/api/health',{signal,credentials:'same-origin'});
 if(!response.ok)throw new Error(`Go API returned HTTP ${response.status}`);
 const data:unknown=await response.json();
 if(typeof data!=='object'||data===null||!('status' in data)||data.status!=='ok'||!('database' in data)||data.database!=='connected'||!('mode' in data)||data.mode!=='read-only')throw new Error('Unexpected Go health response');
 return data as Health;
}
