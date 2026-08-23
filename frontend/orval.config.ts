import { defineConfig } from "orval";

export default defineConfig({
  securitiesManager: {
    input: "../backend/storage/api-docs/api-docs.json",
    output: {
      mode: "tags-split",
      target: "lib/api/generated",
      schemas: "lib/api/generated/models",
      client: "react-query",
      httpClient: "fetch",
      clean: true,
      override: {
        mutator: {
          path: "./lib/api/custom-instance.ts",
          name: "customInstance",
        },
      },
    },
  },
});
