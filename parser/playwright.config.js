export default {
  timeout: Number(process.env.YANDEX_PARSER_TIMEOUT || 180) * 1000,
  use: {
    browserName: 'chromium',
    headless: true
  }
};
