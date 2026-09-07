const fs = require('fs');
const path = require('path');
const XLSX = require('xlsx');

// -------------------------------------------------------------
// 1. Text Normalization & Row Value Helpers
// -------------------------------------------------------------

function cleanString(str) {
  if (!str) return "";
  return String(str)
    .toUpperCase()
    // Remove common PSGC prefixes
    .replace(/\b(BRGY|BARANGAY|CITY OF|MUNICIPALITY OF|PROVINCE OF|REGION)\b/g, '')
    // Remove non-alphanumeric characters except spaces
    .replace(/[^A-Z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function formatToIsoDate(val) {
  if (val === null || val === undefined || val === '') return null;

  // Handle Excel Serial Numbers (e.g. 25888 or "25888")
  if (!isNaN(val) && !isNaN(parseFloat(val))) {
    const num = Number(val);
    const parsedDate = XLSX.SSF.parse_date_code(num);
    if (parsedDate) {
      const yyyy = parsedDate.y;
      const mm = String(parsedDate.m).padStart(2, '0');
      const dd = String(parsedDate.d).padStart(2, '0');
      return `${yyyy}-${mm}-${dd}`;
    }
  }

  // Handle String Dates
  const dateObj = new Date(val);
  if (!isNaN(dateObj.getTime())) {
    return dateObj.toISOString().split('T')[0];
  }

  return null;
}

function getRowValue(row, targetKeywords) {
  for (const key of Object.keys(row)) {
    // Normalize header: remove newlines, extra spaces, and convert to uppercase
    const normalizedHeader = key
      .replace(/[\r\n]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .toUpperCase();

    for (const keyword of targetKeywords) {
      const normalizedKeyword = keyword
        .replace(/[\r\n]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toUpperCase();

      if (normalizedHeader.includes(normalizedKeyword)) {
        return row[key];
      }
    }
  }
  return "";
}

// -------------------------------------------------------------
// 2. Load Reference Maps (PSGC, Positions, Items)
// -------------------------------------------------------------
let dynamicDefaultRegionId = null;
let dynamicDefaultProvinceId = null;
let dynamicDefaultCitymunId = null;
let dynamicDefaultBrgyId = null;

function loadPsgcMap(relativePath, idField) {
  const fullPath = path.join(__dirname, relativePath);
  const map = new Map();

  if (fs.existsSync(fullPath)) {
    const data = JSON.parse(fs.readFileSync(fullPath, 'utf8'));
    
    // Capture the very first valid primary key as the fallback safety ID for this table
    if (data.length > 0) {
      const firstId = data[0][idField];
      if (idField === 'reg_id') dynamicDefaultRegionId = firstId;
      if (idField === 'prov_id') dynamicDefaultProvinceId = firstId;
      if (idField === 'city_id') dynamicDefaultCitymunId = firstId;
      if (idField === 'brgy_id') dynamicDefaultBrgyId = firstId;
    }

    data.forEach(item => {
      const cleanName = cleanString(item.name);
      const altName = cleanString(item.altName);

      const payload = {
        id: item[idField],
        reg_id: item.reg_id || null,
        prov_id: item.prov_id || null,
        city_id: item.city_id || null
      };

      if (cleanName) map.set(cleanName, payload);
      if (altName) map.set(altName, payload);
    });
    console.log(`Loaded ${map.size} records from ${path.basename(relativePath)} using PK '${idField}'.`);
  } else {
    console.warn(`Warning: Could not find PSGC dump file at ${fullPath}`);
  }
  return map;
}

// Load PSGC Maps
const regionMap   = loadPsgcMap('psgc_regions_1q23.json', 'reg_id');
const provinceMap = loadPsgcMap('psgc_provinces_1q23.json', 'prov_id');
const cityMap     = loadPsgcMap('psgc_cities_1q23.json', 'city_id');
const barangayMap = loadPsgcMap('psgc_barangays_1q23.json', 'brgy_id');

// A. Load Positions Map (id -> title)
const positionsFilePath = path.join(__dirname, 'positions.json');
const positionIdToTitleMap = new Map();

if (fs.existsSync(positionsFilePath)) {
  const positionsData = JSON.parse(fs.readFileSync(positionsFilePath, 'utf8'));
  positionsData.forEach(pos => {
    if (pos.id && pos.title) {
      positionIdToTitleMap.set(pos.id, cleanString(pos.title));
    }
  });
  console.log(`Loaded ${positionIdToTitleMap.size} positions.`);
} else {
  console.warn(`Warning: Could not find positions file at ${positionsFilePath}`);
}

// B. Load Items Map storing complete item objects for organizational hierarchy extraction
const itemsFilePath = path.join(__dirname, 'items.json');
const itemCompositeToObjMap = new Map();
const itemNumberOnlyObjMap = new Map(); // Fallback map

if (fs.existsSync(itemsFilePath)) {
  const itemsData = JSON.parse(fs.readFileSync(itemsFilePath, 'utf8'));
  itemsData.forEach(item => {
    if (item.number !== undefined && item.number !== null) {
      const cleanNum = String(item.number).trim().toLowerCase();
      
      // Resolve position title from positionIdToTitleMap
      const positionTitle = positionIdToTitleMap.get(item.position_id) || "";

      if (positionTitle) {
        const compositeKey = `${cleanNum}|${positionTitle}`;
        itemCompositeToObjMap.set(compositeKey, item);
      }

      // Store in fallback map if key doesn't exist yet
      if (!itemNumberOnlyObjMap.has(cleanNum)) {
        itemNumberOnlyObjMap.set(cleanNum, item);
      }
    }
  });
  console.log(`Loaded ${itemsData.length} items (${itemCompositeToObjMap.size} unique composite keys).`);
}

// -------------------------------------------------------------
// 3. Hierarchical Address Parsing Logic
// -------------------------------------------------------------
function parseFullAddress(rawAddress) {
  const result = {
    region_id: null,
    province_id: null,
    citymun_id: null,
    brgy_id: null
  };

  if (!rawAddress) return result;

  const tokens = String(rawAddress)
    .split(',')
    .map(t => cleanString(t))
    .filter(Boolean);

  // 1. Match Barangay & Hierarchically Populate Connected City/Mun, Prov, Reg
  for (const token of tokens) {
    if (barangayMap.has(token)) {
      const brgyData = barangayMap.get(token);
      result.brgy_id = brgyData.id;
      if (brgyData.city_id) result.citymun_id = brgyData.city_id;
      if (brgyData.prov_id) result.province_id = brgyData.prov_id;
      if (brgyData.reg_id)  result.region_id = brgyData.reg_id;
      break;
    }
  }

  // 2. Match City / Municipality (If not already determined by barangay)
  if (!result.citymun_id) {
    for (const token of tokens) {
      if (cityMap.has(token)) {
        const cityData = cityMap.get(token);
        result.citymun_id = cityData.id;
        if (!result.province_id) result.province_id = cityData.prov_id;
        if (!result.region_id)   result.region_id = cityData.reg_id;
        break;
      }
      
      for (const [cityName, cityData] of cityMap.entries()) {
        if (cityName.includes(token) || token.includes(cityName)) {
          result.citymun_id = cityData.id;
          if (!result.province_id) result.province_id = cityData.prov_id;
          if (!result.region_id)   result.region_id = cityData.reg_id;
          break;
        }
      }
      if (result.citymun_id) break;
    }
  }

  // 3. Match Province (If not already determined by city or barangay)
  if (!result.province_id) {
    for (const token of tokens) {
      if (provinceMap.has(token)) {
        const provData = provinceMap.get(token);
        result.province_id = provData.id;
        if (!result.region_id) result.region_id = provData.reg_id;
        break;
      }
    }
  }

  // 4. Match Region (If not already determined above)
  if (!result.region_id) {
    for (const token of tokens) {
      if (regionMap.has(token)) {
        result.region_id = regionMap.get(token).id;
        break;
      }
    }
  }

  return result;
}

// -------------------------------------------------------------
// 4. Read Excel Data & Map Output Arrays
// -------------------------------------------------------------
const excelFilePath = path.join(__dirname, 'employee_data.xlsx');
if (!fs.existsSync(excelFilePath)) {
  console.error(`Error: Could not find Excel file at: ${excelFilePath}`);
  process.exit(1);
}

const workbook = XLSX.readFile(excelFilePath);
const sheetName = workbook.SheetNames[0];
const rawRows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { defval: "" });

const basicDetailsList = [];
const employeesList = [];
const addressesList = [];
const contactsList = [];
const educationList = [];
const eligibilityList = [];
const questionsList = [];
const workExperienceList = [];

let basicDetailPk = 1;
let employeePk = 1;
let addressPk = 1;
let contactPk = 1;
let educationPk = 1;
let eligibilityPk = 1;
let questionPk = 1;
let workExpPk = 1;

// Pre-collect all original ID numbers from Excel to avoid generating collisions
const allExcelIds = new Set(
  rawRows.map(r => cleanString(getRowValue(r, ["ID NO.", "ID NUMBERS", "ID NO"]))).filter(Boolean)
);

// Keep track of IDs assigned so far
const usedIdNumbers = new Set();

const now = new Date().toISOString();

rawRows.forEach((row) => {
  const firstName = cleanString(getRowValue(row, ["FIRST NAME"]));
  const lastName = cleanString(getRowValue(row, ["LAST NAME"]));

  if (!firstName || !lastName) return;

  const basicDetailId = basicDetailPk++;

  const rawBirthday = getRowValue(row, ["DATE OF BIRTH", "BIRTHDAY"]);
  const formattedBirthday = formatToIsoDate(rawBirthday);
  const birthday = (formattedBirthday && formattedBirthday !== 'null') ? formattedBirthday : '1900-01-01';

  const rawSex = cleanString(getRowValue(row, ["SEX"])).toUpperCase();
  const sex = (rawSex.includes('FEMALE') || rawSex === 'F') ? 'Female' : 'Male';

  const rawCivilStatus = cleanString(getRowValue(row, ["CIVIL STATUS"])).toUpperCase();
  let civilStatus = 'Single';
  if (!['MALE', 'FEMALE', 'MEN', 'WOMEN'].includes(rawCivilStatus)) {
    if (rawCivilStatus.includes('MARRIED')) civilStatus = 'Married';
    else if (rawCivilStatus.includes('WIDOW')) civilStatus = 'Widowed';
    else if (rawCivilStatus.includes('DIVORC')) civilStatus = 'Divorced';
    else if (rawCivilStatus.includes('SEPARAT')) civilStatus = 'Separated';
  }

  // Table 1: individual_basic_details
  basicDetailsList.push({
    id: basicDetailId,
    first_name: firstName,
    last_name: lastName,
    middle_name: cleanString(getRowValue(row, ["MIDDLE NAME"])) || null,
    ext_name: cleanString(getRowValue(row, ["EXTENSION NAME", "EXT NAME"]))?.replace(/\./g, '').substring(0, 3) || null,
    birthday: birthday,
    sex: sex,
    civil_status: civilStatus,
    citizenship: cleanString(getRowValue(row, ["CITIZENSHIP"]))?.toUpperCase() || 'FILIPINO',
    citizenship_acquisition: null,
    country_id: null,
    place_of_birth: cleanString(getRowValue(row, ["PLACE OF BIRTH", "POB"])) || 'N/A',
    height: parseFloat(getRowValue(row, ["HEIGHT"])) || 0,
    weight: parseFloat(getRowValue(row, ["WEIGHT"])) || 0,
    blood_type: cleanString(getRowValue(row, ["BLOOD TYPE", "BLOODTYPE"])) || 'A+',
    gsis_no: cleanString(getRowValue(row, ["GSIS NO", "GSIS"])) || null,
    pag_ibig_no: cleanString(getRowValue(row, ["PAG IBIG NO", "PAGIBIG", "HDMF"])) || 'N/A',
    philhealth_no: cleanString(getRowValue(row, ["PHILHEALTH NO", "PHILHEALTH"])) || 'N/A',
    sss_no: cleanString(getRowValue(row, ["SSS NO", "SSS"])) || 'N/A',
    tin: cleanString(getRowValue(row, ["TIN"])) || 'N/A',
    created_at: now,
    updated_at: now,
    deleted_at: null
  });

  // Table 2: employees
  let idNum = cleanString(getRowValue(row, ["ID NO.", "ID NUMBERS", "ID NO"]));
  const excelItemNumber = String(getRowValue(row, [
    "ITEM NUMBER\n(ALL STATUS OF EMPLOYMENT)",
    "ITEM NUMBER (ALL STATUS OF EMPLOYMENT)",
    "ALL STATUS OF EMPLOYMENT",
    "ITEM NUMBER", 
    "ITEM ID", 
    "ITEM NO.", 
    "ITEM NO"
  ])).trim().toLowerCase();

  const excelPositionTitle = cleanString(getRowValue(row, ["POSITION TITLE", "POSITION"]));

  const sg = getRowValue(row, ["SALARY GRADE"]);

  // Item Object Matching Logic (Item ID + Organizational Units)
  let matchedItem = null;
  if (excelItemNumber) {
    const compositeKey = `${excelItemNumber}|${excelPositionTitle}`;

    // 1. First priority: Match both Item Number AND Position Title
    if (itemCompositeToObjMap.has(compositeKey)) {
      matchedItem = itemCompositeToObjMap.get(compositeKey);
    } 
    // 2. Fallback: Match by Item Number alone if exact position title isn't found
    else if (itemNumberOnlyObjMap.has(excelItemNumber)) {
      matchedItem = itemNumberOnlyObjMap.get(excelItemNumber);
    }
  }

  // Extract office_id, division_id, and section_or_unit_id directly from the matched item record
  const itemId = matchedItem ? matchedItem.id : null;
  const officeId = matchedItem && matchedItem.office_id ? matchedItem.office_id : 1;
  const divisionId = matchedItem && matchedItem.division_id ? matchedItem.division_id : 1;
  const sectionOrUnitId = matchedItem && (matchedItem.section_or_unit_id || matchedItem.section_id) 
    ? (matchedItem.section_or_unit_id || matchedItem.section_id) 
    : 1;

  // Duplicate collision resolver for ID numbers
  if (idNum) {
    if (usedIdNumbers.has(idNum)) {
      let count = 1;
      let baseId = idNum.slice(0, -1);
      let candidate = `${baseId}${count}`;

      while (usedIdNumbers.has(candidate) || allExcelIds.has(candidate)) {
        count++;
        candidate = `${baseId}${count}`;
      }

      idNum = candidate;
    }
    usedIdNumbers.add(idNum);
  } else {
    idNum = null;
  }

  if (idNum || excelItemNumber || sg) {
    employeesList.push({
      id: employeePk++,
      individual_basic_detail_id: basicDetailId,
      office_id: officeId,
      division_id: divisionId,
      section_or_unit_id: sectionOrUnitId,
      agency_employee_no: idNum,
      id_number: idNum,
      item_id: itemId,
      salary_grade_id: sg || null,
      created_at: now,
      updated_at: now
    });
  }

  // Table 3: individual_addresses
  const resAddr = getRowValue(row, ["RESIDENTIAL ADDRESS", "RESIDENTIAL ADDR"]);
  const permAddr = getRowValue(row, ["PERMANENT ADDRESS", "PERMANENT ADDR"]);

  const resParsed = parseFullAddress(resAddr);
  const permParsed = parseFullAddress(permAddr);

  if (resAddr || permAddr) {
    addressesList.push({
      id: addressPk++,
      individual_basic_detail_id: basicDetailId,
      residential_house_block_lot_no: null,
      residential_street: resAddr || null,
      residential_subdivision_village: null,
      
      residential_brgy_id: resParsed.brgy_id || dynamicDefaultBrgyId,
      residential_citymun_id: resParsed.citymun_id || dynamicDefaultCitymunId,
      residential_province_id: resParsed.province_id || dynamicDefaultProvinceId,
      residential_region_id: resParsed.region_id || dynamicDefaultRegionId,
      residential_zip_code: '0000',

      permanent_house_block_lot_no: null,
      permanent_street: permAddr || resAddr || null,
      permanent_subdivision_village: null,

      permanent_brgy_id: permParsed.brgy_id || resParsed.brgy_id || dynamicDefaultBrgyId,
      permanent_citymun_id: permParsed.citymun_id || resParsed.citymun_id || dynamicDefaultCitymunId,
      permanent_province_id: permParsed.province_id || resParsed.province_id || dynamicDefaultProvinceId,
      permanent_region_id: permParsed.region_id || resParsed.region_id || dynamicDefaultRegionId,
      permanent_zip_code: '0000',

      created_at: now,
      updated_at: now,
      deleted_at: null
    });
  }

  // Table 4: individual_contact_infos
  const mobile = getRowValue(row, ["MOBILE NUMBER", "MOBILE NO"]);
  const email = getRowValue(row, ["EMAIL ADDRESS", "PERSONAL EMAIL"]);
  if (mobile || email) {
    contactsList.push({
      id: contactPk++,
      individual_basic_detail_id: basicDetailId,
      tel_no: null,
      mobile_no: mobile || null,
      email_address: email || null,
      created_at: now,
      updated_at: now,
      deleted_at: null
    });
  }

  // Table 5: individual_educational_backgrounds
  const rawEduLevel = getRowValue(row, ["EDUCATION LEVEL", "BASIC EDUCATION", "DEGREE", "COURSE"]);
  
  if (rawEduLevel) {
    const upperEdu = cleanString(rawEduLevel);
    
    let normalizedLevel = 'College';

    if (upperEdu.includes('ELEM') || upperEdu.includes('PRIMARY')) {
      normalizedLevel = 'Elementary';
    } else if (upperEdu.includes('HIGH') || upperEdu.includes('SECOND') || upperEdu.includes('HS')) {
      normalizedLevel = 'Secondary';
    } else if (upperEdu.includes('VOCAT') || upperEdu.includes('TRADE') || upperEdu.includes('TESDA')) {
      normalizedLevel = 'Vocational';
    } else if (upperEdu.includes('MASTER') || upperEdu.includes('DOCTOR') || upperEdu.includes('POST') || upperEdu.includes('GRADUATE STUDIES')) {
      normalizedLevel = 'Graduate Studies';
    } else if (upperEdu.includes('COLLEGE') || upperEdu.includes('BACHELOR') || upperEdu.includes('BS') || upperEdu.includes('BA')) {
      normalizedLevel = 'College';
    }

    educationList.push({
      id: educationPk++,
      individual_basic_detail_id: basicDetailId,
      level: normalizedLevel,
      schools_name: null,
      education_description: upperEdu,
      period_of_attendance_from: null,
      period_of_attendance_to: null,
      highest_level_units_earned: null,
      year_graduated: null,
      scholarship_academic_honors_received: null,
      created_at: now,
      updated_at: now,
      deleted_at: null
    });
  }

  // Table 7: individual_questions
  questionsList.push({
    id: questionPk++,
    individual_basic_detail_id: basicDetailId,
    q34_a: false,
    q34_b: false,
    q34_details: null,
    q35_a: false,
    q35_a_details: null,
    q35_b: false,
    q35_b_date_filed: null,
    q35_b_status: null,
    q36: false,
    q36_details: null,
    q37: false,
    q37_details: null,
    q38_a: false,
    q38_a_details: null,
    q38_b: false,
    q38_b_details: null,
    q39: false,
    q40_a_indigenous_group: false,
    q40_a_details: null,
    q40_b_pwd: false,
    q40_b_details: null,
    q40_c_solo_parent: false,
    q40_c_details: null,
    country_id: null,
    created_at: now,
    updated_at: now,
    deleted_at: null
  });

  // Table 8: individual_work_experiences
  const posTitle = getRowValue(row, ["POSITION TITLE"]);
  const rawApptDate = getRowValue(row, ["DATE OF ORIGINAL APPOINTMENT", "DATE OF CREATION"]);
  const officeName = getRowValue(row, ["OFFICE", "FO MAIN", "RRCY"]);

  if (posTitle || rawApptDate) {
    let formattedFromDate = formatToIsoDate(rawApptDate);

    if (!formattedFromDate && rawApptDate) {
      const trimmedDate = String(rawApptDate).trim();
      if (/^\d{4}$/.test(trimmedDate)) {
        formattedFromDate = `${trimmedDate}-01-01`;
      }
    }

    const rawStatus = cleanString(getRowValue(row, ["STATUS OF EMPLOYMENT", "ALL STATUS OF EMPLOYMENT", "STATUS"]));

    let normalizedStatus = 'PERMANENT';

    if (rawStatus.includes('CASUAL')) {
      normalizedStatus = 'CASUAL';
    } else if (rawStatus.includes('CONTRACT') || rawStatus.includes('COS')) {
      normalizedStatus = 'CONTRACTUAL';
    } else if (rawStatus.includes('COTERMINOUS') || rawStatus.includes('CO TERMINOUS')) {
      normalizedStatus = 'COTERMINOUS';
    } else if (rawStatus.includes('JOB') || rawStatus.includes('JO')) {
      normalizedStatus = 'JOB ORDER';
    } else if (rawStatus.includes('PERMANENT') || rawStatus.includes('REGULAR')) {
      normalizedStatus = 'PERMANENT';
    } else if (rawStatus.includes('NA') || rawStatus.includes('N A') || rawStatus.includes('NONE') || !rawStatus) {
      normalizedStatus = 'PERMANENT';
    }

    workExperienceList.push({
      id: workExpPk++,
      individual_basic_detail_id: basicDetailId,
      inclusive_date_from: formattedFromDate || '1900-01-01',
      inclusive_date_to: formattedFromDate || null,
      position_title: posTitle || null,
      department_agency_office_company: officeName || null,
      monthly_salary: null,
      custom_salary_grade: sg || null,
      salary_grade_id: null,
      status_of_appointment: normalizedStatus,
      is_gov_service: true,
      is_current_work: true,
      office_unit: getRowValue(row, ["SECTION/UNIT", "DIVISION", "PROGRAM"]) || null,
      immediate_supervisor: null,
      summary_of_actual_duties: null,
      significant_accomplishments: null,
      created_at: now,
      updated_at: now,
      deleted_at: null
    });
  }
});

// -------------------------------------------------------------
// 5. Write Data to JSON
// -------------------------------------------------------------
fs.writeFileSync(path.join(__dirname, 'individual_basic_details.json'), JSON.stringify(basicDetailsList, null, 2));
fs.writeFileSync(path.join(__dirname, 'employees.json'), JSON.stringify(employeesList, null, 2));
fs.writeFileSync(path.join(__dirname, 'individual_addresses.json'), JSON.stringify(addressesList, null, 2));
fs.writeFileSync(path.join(__dirname, 'individual_contact_infos.json'), JSON.stringify(contactsList, null, 2));
fs.writeFileSync(path.join(__dirname, 'individual_educational_backgrounds.json'), JSON.stringify(educationList, null, 2));
fs.writeFileSync(path.join(__dirname, 'individual_eligibilities.json'), JSON.stringify(eligibilityList, null, 2));
fs.writeFileSync(path.join(__dirname, 'individual_questions.json'), JSON.stringify(questionsList, null, 2));
fs.writeFileSync(path.join(__dirname, 'individual_work_experiences.json'), JSON.stringify(workExperienceList, null, 2));

console.log(`\nSuccessfully processed ${basicDetailsList.length} records with structural hierarchy mapped directly from items.json.`);