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
const workbook = XLSX.readFile('employee_data.xlsx'); // Ensure filename matches yours
const sheetName = workbook.SheetNames[0];
const rawRows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { defval: "" });

console.log(`Scanning ${rawRows.length} rows to extract OFFICE entries...`);

const uniqueOfficesSet = new Set();a
const uniqueOfficesList = [];

// 2. Extract Unique Office Names
rawRows.forEach(row => {
  // Finds values from the "OFFICE" column regardless of line breaks
  const officeName = String(
    getRowValue(row, ["OFFICE", "FO MAIN", "RRCY"]) || row.office || ""
  ).trim();

  if (!officeName) return;

  const lookupKey = officeName.toLowerCase();
  if (!uniqueOfficesSet.has(lookupKey)) {
    uniqueOfficesSet.add(lookupKey);
    uniqueOfficesList.push(officeName);
  }
});

// 3. Format as Seeder JSON matching your structure: { id, name }
let idCounter = 1;
const officesSeeder = uniqueOfficesList.map(offName => ({
  id: idCounter++,
  name: offName
}));

// 4. Save to offices.json
fs.writeFileSync('offices.json', JSON.stringify(officesSeeder, null, 2));

console.log(`\n Success! Created 'offices.json' with ${officesSeeder.length} unique office records.`);