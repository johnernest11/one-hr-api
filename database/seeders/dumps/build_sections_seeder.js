const fs = require('fs');
const XLSX = require('xlsx');

// 1. Load Divisions to map division names to division_ids
const divisions = JSON.parse(fs.readFileSync('divisions.json', 'utf8'));

// Helper for fuzzy text normalization
function normalizeText(str) {
  if (!str) return "";
  return String(str)
    .toLowerCase()
    .replace(/\b(division|section|unit|office of the|office|department)\b/g, '')
    .replace(/[^a-z0-9]/g, '')
    .trim();
}

function getAcronym(str) {
  if (!str) return "";
  return String(str).split(/\s+/).map(w => w[0]).join('').toLowerCase();
}

// Map divisions
const divisionMap = new Map();
divisions.forEach(d => {
  const raw = String(d.name || d.title || "").trim();
  if (!raw) return;
  divisionMap.set(raw.toLowerCase(), d.id);
  divisionMap.set(normalizeText(raw), d.id);
  const acr = getAcronym(raw);
  if (acr.length > 1) divisionMap.set(acr, d.id);
});

// Helper function to safely read column values
function getRowValue(row, keys) {
  for (const key of Object.keys(row)) {
    const cleanKey = key.replace(/\r?\n|\r/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
    for (const searchKey of keys) {
      if (cleanKey.includes(searchKey.toUpperCase())) {
        return row[key];
      }
    }
  }
  return "";
}

// 2. Read Excel Data
const workbook = XLSX.readFile('employee_data.xlsx');
const sheetName = workbook.SheetNames[0];
const rawRows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { defval: "" });

const uniqueSectionsMap = new Map();

// 3. Extract unique Sections and link to Division IDs
rawRows.forEach(row => {
  const secName = String(getRowValue(row, ["SECTION/UNIT", "SECTION"]) || row.section_or_unit || "").trim();
  const divRaw = String(getRowValue(row, ["DIVISION"]) || row.division_name || row.division || "").trim();

  if (!secName) return;

  // Resolve division_id
  const divLower = divRaw.toLowerCase();
  const divNorm = normalizeText(divRaw);
  const divAcr = getAcronym(divRaw);
  const matchedDivId = divisionMap.get(divLower) || divisionMap.get(divNorm) || divisionMap.get(divAcr) || null;

  // Normalize key to avoid duplicate entries for same section name
  const uniqueKey = secName.toLowerCase();

  if (!uniqueSectionsMap.has(uniqueKey)) {
    uniqueSectionsMap.set(uniqueKey, {
      name: secName,
      division_id: matchedDivId
    });
  }
});

// 4. Format into JSON list with primary keys
let idCounter = 1;
const sectionsSeeder = Array.from(uniqueSectionsMap.values()).map(sec => ({
  id: idCounter++,
  division_id: sec.division_id, // Linked to Division
  name: sec.name,
  code: getAcronym(sec.name).toUpperCase()
}));

// 5. Output file
fs.writeFileSync('sections_or_units.json', JSON.stringify(sectionsSeeder, null, 2));

console.log(`Successfully generated 'sections_or_units.json' with ${sectionsSeeder.length} unique sections linked to their Division IDs!`);