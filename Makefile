.PHONY: localstackup
localstackup:
	@echo "Starting LocalStack..."
	@docker compose -f docker-compose.yml -f docker-compose.localstack.yml up
