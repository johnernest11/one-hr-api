const fs = require('fs');
const XLSX = require('xlsx');

// Helper to find column values regardless of multiline line-breaks or extra spaces
function getRowValue(row, targetKeywords) {
  for (const key of Object.keys(row)) {
    // Normalizes header string: strips out newlines (\n) and double spaces
    const normalizedHeader = key.replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
    
    for (const keyword of targetKeywords) {
      if (normalizedHeader.includes(keyword.toUpperCase())) {
        return row[key];
      }
    }
  }
  return "";
}

// 1. Read Excel Data
const workbook = XLSX.readFile('employee_data.xlsx');
const sheetName = workbook.SheetNames[0];
const rawRows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { defval: "" });

console.log(`Scanning ${rawRows.length} rows to extract PROGRAM entries...`);

const uniqueProgramsSet = new Set();
const uniqueProgramsList = [];

// 2. Extract Unique Program Names
rawRows.forEach(row => {
  // Finds values from the "PROGRAM" column regardless of line breaks
  const programName = String(
    getRowValue(row, ["PROGRAM"]) || row.program || ""
  ).trim();

  if (!programName) return;

  const lookupKey = programName.toLowerCase();
  if (!uniqueProgramsSet.has(lookupKey)) {
    uniqueProgramsSet.add(lookupKey);
    uniqueProgramsList.push(programName);
  }
});

// 3. Format as Seeder JSON matching your structure: { id, name }
let idCounter = 1;
const programsSeeder = uniqueProgramsList.map(progName => ({
  id: idCounter++,
  name: progName
}));

// 4. Save to programs.json
fs.writeFileSync('programs.json', JSON.stringify(programsSeeder, null, 2));

console.log(`\n Success! Created 'programs.json' with ${programsSeeder.length} unique program records.`);