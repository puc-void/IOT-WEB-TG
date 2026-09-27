/* ===============================================================
   RFID Attendance System (Final Stable - Non-Flickering OLED)
   ESP32 + RC522 + SH1106 OLED + XAMPP
   =============================================================== */

#include <HTTPClient.h>
#include <WiFi.h>
#include <time.h>

#include <MFRC522.h>
#include <SPI.h>

#include <Adafruit_GFX.h>
#include <Adafruit_SH110X.h>
#include <Wire.h>

/* ---------------- PIN CONFIG ---------------- */

// OLED I2C pins
#define OLED_SDA 21
#define OLED_SCL 22

// RC522 pins
#define RC522_SS 5
#define RC522_RST 4

// SH1106 OLED object (128x64)
Adafruit_SH1106G display(128, 64, &Wire);

/* ---------------- WIFI CONFIG ---------------- */

const char *ssid = "narzo 50";
const char *password = "92837465";

// XAMPP server URL (Replace IP if needed)
String serverURL = "http://192.168.1.14/Attendance%20Management/getdata.php";

// Device Token from DB
const char *device_token = "19453d39b4edd4e6";

/* ---------------- GLOBAL VARS ---------------- */

MFRC522 rfid(RC522_SS, RC522_RST);
String lastCard = "";
unsigned long lastClearTime = 0;
unsigned long lastDisplayUpdate = 0;

/* ================= FUNCTION PROTOTYPES ================= */
void showMessage(String title, String subtitle, int delayMs = 0);
void updateIdleScreen();
void sendUID(String uid);
void connectWiFi();
String getCardUID();

/* ================= SETUP ================= */

void setup() {
  Serial.begin(115200);
  delay(300);

  // Start I2C for OLED
  Wire.begin(OLED_SDA, OLED_SCL);

  // Init SH1106 OLED
  display.begin(0x3C, true);
  display.clearDisplay();
  display.setTextColor(SH110X_WHITE);
  display.display();

  Serial.println("\n===========================================");
  Serial.println("   ESP32 RFID Attendance System Starting   ");
  Serial.println("===========================================");

  // Init RC522 RFID
  SPI.begin();
  rfid.PCD_Init();
  Serial.println("[HARDWARE] RC522 RFID Reader Ready.");

  showMessage("RFID SYSTEM", "Connecting WiFi...", 0);
  connectWiFi();

  // Configure Bangladesh Time (GMT+6)
  configTime(6 * 3600, 0, "pool.ntp.org", "time.nist.gov");

  showMessage("SYSTEM READY", "Place Card...", 1500);
}

/* ================= MAIN LOOP ================= */

void loop() {
  // Reconnect WiFi if dropped
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
  }

  // Clear card debounce memory every 10 seconds
  if (millis() - lastClearTime > 10000) {
    lastCard = "";
    lastClearTime = millis();
  }

  // Update non-flickering Idle screen every 1 second
  if (millis() - lastDisplayUpdate >= 1000) {
    updateIdleScreen();
    lastDisplayUpdate = millis();
  }

  // Check if RFID card is present
  if (!rfid.PICC_IsNewCardPresent())
    return;
  if (!rfid.PICC_ReadCardSerial())
    return;

  String uid = getCardUID();

  // Prevent instant repeat scans of the exact same card
  if (uid == lastCard) {
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
    return;
  }
  lastCard = uid;

  // Print Card Details to Serial Monitor
  Serial.println("\n-------------------------------------------");
  Serial.print("[RFID CARD DETECTED] UID: ");
  Serial.println(uid);

  // Show "Card Scanned" on OLED
  showMessage("CARD SCANNED", "UID: " + uid, 400);

  // Send request to PHP Backend
  sendUID(uid);

  // Halt RFID PICC
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
}

/* ================= HELPER FUNCTIONS ================= */

// Convert UID to HEX string (no spaces)
String getCardUID() {
  String id = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    char hexByte[4];
    sprintf(hexByte, "%02X", rfid.uid.uidByte[i]);
    id += hexByte;
  }
  return id;
}

// Clean, non-flickering Idle display showing Clock & Status
void updateIdleScreen() {
  display.clearDisplay();

  // Header
  display.setTextSize(1);
  display.setCursor(10, 2);
  display.println("ESP32 RFID SYSTEM");
  display.drawLine(0, 12, 128, 12, SH110X_WHITE);

  // Clock in Center
  time_t now = time(nullptr);
  struct tm *t = localtime(&now);

  if (t && t->tm_year > 100) { // Valid time acquired
    display.setTextSize(2);
    display.setCursor(18, 22);

    if (t->tm_hour < 10)
      display.print("0");
    display.print(t->tm_hour);
    display.print(":");
    if (t->tm_min < 10)
      display.print("0");
    display.print(t->tm_min);
    display.print(":");
    if (t->tm_sec < 10)
      display.print("0");
    display.print(t->tm_sec);
  } else {
    display.setTextSize(1);
    display.setCursor(25, 25);
    display.println("Syncing Time...");
  }

  // Footer text
  display.drawLine(0, 48, 128, 48, SH110X_WHITE);
  display.setTextSize(1);
  display.setCursor(15, 52);
  display.println(">> Place Card <<");

  display.display();
}

// Send Card UID & Device Token to Server via GET
void sendUID(String uid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[ERROR] WiFi Lost! Cannot process scan.");
    showMessage("WIFI ERROR", "WiFi Connection Lost", 2000);
    return;
  }

  HTTPClient http;
  String fullURL =
      serverURL + "?card_uid=" + uid + "&device_token=" + device_token;

  Serial.print("[HTTP GET] Request: ");
  Serial.println(fullURL);

  showMessage("SENDING DATA", "UID: " + uid, 0);

  http.begin(fullURL);
  http.addHeader("User-Agent", "ESP32-RFID");
  http.setConnectTimeout(5000);

  int httpCode = http.GET();
  Serial.print("[HTTP CODE] Status: ");
  Serial.println(httpCode);

  if (httpCode <= 0) {
    Serial.println("[ERROR] Server Connection Failed!");
    showMessage("SERVER ERROR", "Connection Failed", 2500);
    http.end();
    return;
  }

  String response = http.getString();
  response.trim();
  Serial.print("[SERVER RESPONSE] Raw: ");
  Serial.println(response);

  // --- HANDLE SERVER RESPONSES ---

  if (response.startsWith("login")) {
    String name = response.substring(5);
    Serial.print("[SUCCESS] CHECK-IN RECORDED for Student: ");
    Serial.println(name);

    showMessage("WELCOME!", name, 3000);
  } 
  else if (response.startsWith("logout")) {
    String name = response.substring(6);
    Serial.print("[SUCCESS] CHECK-OUT RECORDED for Student: ");
    Serial.println(name);

    showMessage("GOOD BYE!", name, 3000);
  } 
  else if (response == "Not Registered") {
    // UNREGISTERED CARD DENIAL
    Serial.println("[ACCESS DENIED] Unregistered Card! Scan Not Allowed.");

    showMessage("ACCESS DENIED", "Not Registered!", 3000);
  } 
  else if (response == "Invalid Token") {
    // SECURITY TOKEN MISMATCH
    Serial.println("[SECURITY ERROR] Invalid Device Token!");

    showMessage("SECURITY ERR", "Invalid Token!", 3000);
  } 
  else if (response == "Already Out") {
    Serial.println("[LIMIT EXCEEDED] Student already checked out for today!");

    showMessage("DAY LIMIT", "Already Out Today", 3000);
  } 
  else if (response == "succesful") {
    Serial.println("[REGISTRATION MODE] New Card Scanned!");

    showMessage("REGISTRATION", "New Card Scanned", 3000);
  } 
  else if (response == "available") {
    Serial.println("[REGISTRATION MODE] Card Available / Free!");

    showMessage("REGISTRATION", "Card Available", 3000);
  } 
  else {
    Serial.print("[SERVER MESSAGE] ");
    Serial.println(response);

    showMessage("SERVER MSG", response, 3000);
  }

  http.end();
}

// Connect to WiFi network
void connectWiFi() {
  Serial.print("[WIFI] Connecting to ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 25) {
    delay(300);
    Serial.print(".");
    attempts++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("[WIFI] Connected Successfully!");
    Serial.print("[WIFI] ESP32 IP Address: ");
    Serial.println(WiFi.localIP());

    showMessage("WIFI CONNECTED", WiFi.localIP().toString(), 1500);
  } else {
    Serial.println("[WIFI] Connection Failed!");
    showMessage("WIFI FAILED", "Check Password/AP", 2000);
  }
}

// Display Clean Message Box without Screen Flickering
void showMessage(String title, String subtitle, int delayMs) {
  display.clearDisplay();

  // Title Box
  display.setTextSize(2);
  display.setCursor(0, 5);
  display.println(title);

  // Subtitle
  display.setTextSize(1);
  display.setCursor(0, 38);
  display.println(subtitle);

  display.display();

  if (delayMs > 0) {
    delay(delayMs);
  }
}
